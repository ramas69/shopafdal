<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\OrderStatus;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ClientAnalyticsController extends AbstractController
{
    private const SQL_DATE_FORMAT = 'Y-m-d H:i:s';

    #[Route('/analytics', name: 'app_client_analytics')]
    public function analytics(Connection $conn): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $company = $user->getCompany();
        if ($company === null) {
            throw $this->createNotFoundException('Aucune entreprise rattachée à ce compte.');
        }
        $companyId = $company->getId();

        $now = new \DateTimeImmutable();
        $monthly = $this->buildMonthlyTimeline($conn, $companyId, $now);

        $topProducts = $conn->fetchAllAssociative(
            "SELECT p.name, SUM(oi.quantity) AS qty, SUM(oi.unit_price_cents * oi.quantity) AS revenue
             FROM order_items oi
             JOIN product_variants v ON v.id = oi.variant_id
             JOIN products p ON p.id = v.product_id
             JOIN orders o ON o.id = oi.order_id
             WHERE o.company_id = :company AND o.status != 'cancelled'
             GROUP BY p.id, p.name
             ORDER BY qty DESC LIMIT 5",
            ['company' => $companyId],
        );

        $statusDist = $conn->fetchAllAssociative(
            "SELECT status, COUNT(*) AS count FROM orders
             WHERE company_id = :company AND status NOT IN ('delivered', 'cancelled')
             GROUP BY status",
            ['company' => $companyId],
        );

        $totals = $conn->fetchAssociative(
            "SELECT COALESCE(SUM(oi.unit_price_cents * oi.quantity), 0) AS revenue,
                    COUNT(DISTINCT o.id) AS orders_count,
                    COUNT(DISTINCT CASE WHEN o.status IN ('placed','confirmed','in_production') THEN o.id END) AS in_progress
             FROM orders o JOIN order_items oi ON oi.order_id = o.id
             WHERE o.company_id = :company AND o.status != 'cancelled'",
            ['company' => $companyId],
        );
        $avgTicket = (int) $totals['orders_count'] > 0
            ? (int) round(((int) $totals['revenue']) / (int) $totals['orders_count'])
            : 0;

        $revenueDeltaPct = $this->computeRevenueDeltaPct($conn, $companyId, $now);

        // Économies réalisées grâce aux tarifs négociés
        $savings = (int) $conn->fetchOne(
            "SELECT COALESCE(SUM(GREATEST(p.base_price_cents - oi.unit_price_cents, 0) * oi.quantity), 0)
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             JOIN product_variants v ON v.id = oi.variant_id
             JOIN products p ON p.id = v.product_id
             WHERE o.company_id = :company AND o.status != 'cancelled'",
            ['company' => $companyId],
        );

        // Commandes par antenne
        $byAntenna = $conn->fetchAllAssociative(
            "SELECT a.name, COUNT(DISTINCT o.id) AS orders_count,
                    COALESCE(SUM(oi.unit_price_cents * oi.quantity), 0) AS revenue
             FROM antennas a
             JOIN orders o ON o.antenna_id = a.id
             JOIN order_items oi ON oi.order_id = o.id
             WHERE o.company_id = :company AND o.status != 'cancelled'
             GROUP BY a.id, a.name
             ORDER BY orders_count DESC",
            ['company' => $companyId],
        );

        // Commandes en retard (date de livraison estimée dépassée, pas livrée)
        $lateOrders = $conn->fetchAllAssociative(
            "SELECT o.reference, o.status, o.estimated_delivery_at
             FROM orders o
             WHERE o.company_id = :company
               AND o.estimated_delivery_at IS NOT NULL
               AND o.estimated_delivery_at < NOW()
               AND o.status NOT IN ('delivered', 'cancelled')
             ORDER BY o.estimated_delivery_at ASC
             LIMIT 5",
            ['company' => $companyId],
        );
        $lateCount = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM orders
             WHERE company_id = :company
               AND estimated_delivery_at IS NOT NULL
               AND estimated_delivery_at < NOW()
               AND status NOT IN ('delivered', 'cancelled')",
            ['company' => $companyId],
        );

        return $this->render('dashboard/analytics.html.twig', [
            'company' => $company,
            'monthly' => $monthly,
            'top_products' => $topProducts,
            'status_dist' => $statusDist,
            'by_antenna' => $byAntenna,
            'late_orders' => $lateOrders,
            'late_count' => $lateCount,
            'totals' => [
                'revenue_cents' => (int) $totals['revenue'],
                'orders_count' => (int) $totals['orders_count'],
                'in_progress_count' => (int) $totals['in_progress'],
                'avg_ticket_cents' => $avgTicket,
                'savings_cents' => $savings,
                'revenue_delta_pct' => $revenueDeltaPct,
            ],
            'status_labels' => array_combine(
                array_map(fn($s) => $s->value, OrderStatus::cases()),
                array_map(fn($s) => $s->label(), OrderStatus::cases()),
            ),
        ]);
    }

    /**
     * Timeline 12 mois : 6 passés (par created_at) + 6 futurs (par estimated_delivery_at).
     */
    private function buildMonthlyTimeline(Connection $conn, int $companyId, \DateTimeImmutable $now): array
    {
        $currentMonthStart = $now->modify('first day of this month')->setTime(0, 0);
        $pastStart = $currentMonthStart->modify('-5 months');
        $futureEnd = $currentMonthStart->modify('+7 months');
        $nextMonthStart = $currentMonthStart->modify('+1 month');

        $actual = $conn->fetchAllAssociative(
            "SELECT DATE_FORMAT(o.created_at, '%Y-%m') AS month,
                    COALESCE(SUM(oi.unit_price_cents * oi.quantity), 0) AS revenue,
                    COUNT(DISTINCT o.id) AS orders_count
             FROM orders o JOIN order_items oi ON oi.order_id = o.id
             WHERE o.company_id = :company AND o.created_at >= :from AND o.created_at < :to AND o.status != 'cancelled'
             GROUP BY month",
            ['company' => $companyId, 'from' => $pastStart->format(self::SQL_DATE_FORMAT), 'to' => $futureEnd->format(self::SQL_DATE_FORMAT)],
        );

        $projected = $conn->fetchAllAssociative(
            "SELECT DATE_FORMAT(o.estimated_delivery_at, '%Y-%m') AS month,
                    COALESCE(SUM(oi.unit_price_cents * oi.quantity), 0) AS revenue,
                    COUNT(DISTINCT o.id) AS orders_count
             FROM orders o JOIN order_items oi ON oi.order_id = o.id
             WHERE o.company_id = :company
               AND o.estimated_delivery_at IS NOT NULL
               AND o.estimated_delivery_at >= :from AND o.estimated_delivery_at < :to
               AND o.status NOT IN ('delivered', 'cancelled')
             GROUP BY month",
            ['company' => $companyId, 'from' => $nextMonthStart->format(self::SQL_DATE_FORMAT), 'to' => $futureEnd->format(self::SQL_DATE_FORMAT)],
        );

        $byMonth = [];
        for ($i = -5; $i <= 6; $i++) {
            $m = $currentMonthStart->modify(sprintf('%+d months', $i))->format('Y-m');
            $byMonth[$m] = ['month' => $m, 'revenue' => 0, 'orders_count' => 0, 'projected' => $i >= 1];
        }
        foreach ([...$actual, ...$projected] as $row) {
            if (isset($byMonth[$row['month']])) {
                $byMonth[$row['month']]['revenue'] = (int) $row['revenue'];
                $byMonth[$row['month']]['orders_count'] = (int) $row['orders_count'];
            }
        }

        return array_values($byMonth);
    }

    private function computeRevenueDeltaPct(Connection $conn, int $companyId, \DateTimeImmutable $now): ?float
    {
        $thisMonth = $now->modify('first day of this month')->setTime(0, 0);
        $lastMonth = $thisMonth->modify('-1 month');
        $revThis = (int) $conn->fetchOne(
            "SELECT COALESCE(SUM(oi.unit_price_cents * oi.quantity), 0)
             FROM orders o JOIN order_items oi ON oi.order_id = o.id
             WHERE o.company_id = :company AND o.status != 'cancelled' AND o.created_at >= :from",
            ['company' => $companyId, 'from' => $thisMonth->format(self::SQL_DATE_FORMAT)],
        );
        $revPrev = (int) $conn->fetchOne(
            "SELECT COALESCE(SUM(oi.unit_price_cents * oi.quantity), 0)
             FROM orders o JOIN order_items oi ON oi.order_id = o.id
             WHERE o.company_id = :company AND o.status != 'cancelled'
               AND o.created_at >= :from AND o.created_at < :to",
            ['company' => $companyId, 'from' => $lastMonth->format(self::SQL_DATE_FORMAT), 'to' => $thisMonth->format(self::SQL_DATE_FORMAT)],
        );

        return $revPrev > 0 ? round((($revThis - $revPrev) / $revPrev) * 100, 1) : null;
    }
}
