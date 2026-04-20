<?php

namespace App\Controller\Admin;

use App\Enum\OrderStatus;
use App\Repository\CompanyRepository;
use App\Repository\OrderRepository;
use App\Service\OrderExporter;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AnalyticsController extends AbstractController
{
    private const SQL_DATE_FORMAT = 'Y-m-d H:i:s';

    #[Route('/analytics', name: 'app_admin_analytics')]
    public function analytics(Connection $conn, OrderRepository $orders): Response
    {
        $now = new \DateTimeImmutable();
        $currentMonthStart = $now->modify('first day of this month')->setTime(0, 0);
        $pastStart = $currentMonthStart->modify('-5 months'); // 6 mois passés (inclus le mois courant)
        $futureEnd = $currentMonthStart->modify('+7 months'); // 6 mois futurs (exclusif)

        // CA passé : par date de création
        $actual = $conn->fetchAllAssociative(
            "SELECT DATE_FORMAT(o.created_at, '%Y-%m') AS month,
                    COALESCE(SUM(oi.unit_price_cents * oi.quantity), 0) AS revenue
             FROM orders o JOIN order_items oi ON oi.order_id = o.id
             WHERE o.created_at >= :from AND o.created_at < :to AND o.status != 'cancelled'
             GROUP BY month",
            ['from' => $pastStart->format(self::SQL_DATE_FORMAT), 'to' => $futureEnd->format(self::SQL_DATE_FORMAT)],
        );

        // CA projeté : par date de livraison estimée (commandes non livrées/annulées)
        $projected = $conn->fetchAllAssociative(
            "SELECT DATE_FORMAT(o.estimated_delivery_at, '%Y-%m') AS month,
                    COALESCE(SUM(oi.unit_price_cents * oi.quantity), 0) AS revenue
             FROM orders o JOIN order_items oi ON oi.order_id = o.id
             WHERE o.estimated_delivery_at IS NOT NULL
               AND o.estimated_delivery_at >= :from AND o.estimated_delivery_at < :to
               AND o.status NOT IN ('delivered', 'cancelled')
             GROUP BY month",
            ['from' => $currentMonthStart->modify('+1 month')->format(self::SQL_DATE_FORMAT), 'to' => $futureEnd->format(self::SQL_DATE_FORMAT)],
        );

        $byMonth = [];
        for ($i = -5; $i <= 6; $i++) {
            $m = $currentMonthStart->modify(sprintf('%+d months', $i))->format('Y-m');
            $byMonth[$m] = ['month' => $m, 'revenue' => 0, 'projected' => $i >= 1, 'orders_count' => 0];
        }
        foreach ($actual as $row) {
            if (isset($byMonth[$row['month']])) {
                $byMonth[$row['month']]['revenue'] = (int) $row['revenue'];
            }
        }
        foreach ($projected as $row) {
            if (isset($byMonth[$row['month']])) {
                $byMonth[$row['month']]['revenue'] = (int) $row['revenue'];
            }
        }
        $monthly = array_values($byMonth);

        // Top 10 produits (quantité)
        $topProducts = $conn->fetchAllAssociative(
            "SELECT p.name, SUM(oi.quantity) AS qty, SUM(oi.unit_price_cents * oi.quantity) AS revenue
             FROM order_items oi
             JOIN product_variants v ON v.id = oi.variant_id
             JOIN products p ON p.id = v.product_id
             JOIN orders o ON o.id = oi.order_id
             WHERE o.status != 'cancelled'
             GROUP BY p.id, p.name
             ORDER BY qty DESC LIMIT 10"
        );

        // Top 10 entreprises (CA)
        $topCompanies = $conn->fetchAllAssociative(
            "SELECT c.name, COUNT(DISTINCT o.id) AS orders_count,
                    COALESCE(SUM(oi.unit_price_cents * oi.quantity), 0) AS revenue
             FROM companies c
             JOIN orders o ON o.company_id = c.id
             JOIN order_items oi ON oi.order_id = o.id
             WHERE o.status != 'cancelled'
             GROUP BY c.id, c.name
             ORDER BY revenue DESC LIMIT 10"
        );

        // Distribution statuts (actifs)
        $statusDist = $conn->fetchAllAssociative(
            "SELECT status, COUNT(*) AS count FROM orders
             WHERE status NOT IN ('delivered', 'cancelled')
             GROUP BY status"
        );

        // Totaux
        $totals = $conn->fetchAssociative(
            "SELECT COALESCE(SUM(oi.unit_price_cents * oi.quantity), 0) AS revenue,
                    COUNT(DISTINCT o.id) AS orders_count,
                    COUNT(DISTINCT CASE WHEN o.status = 'delivered' THEN o.id END) AS delivered
             FROM orders o JOIN order_items oi ON oi.order_id = o.id
             WHERE o.status != 'cancelled'"
        );
        $avgTicket = (int) $totals['orders_count'] > 0
            ? (int) round(((int) $totals['revenue']) / (int) $totals['orders_count'])
            : 0;

        return $this->render('admin/analytics.html.twig', [
            'monthly' => $monthly,
            'top_products' => $topProducts,
            'top_companies' => $topCompanies,
            'status_dist' => $statusDist,
            'totals' => [
                'revenue_cents' => (int) $totals['revenue'],
                'orders_count' => (int) $totals['orders_count'],
                'delivered_count' => (int) $totals['delivered'],
                'avg_ticket_cents' => $avgTicket,
            ],
            'status_labels' => array_combine(
                array_map(fn($s) => $s->value, OrderStatus::cases()),
                array_map(fn($s) => $s->label(), OrderStatus::cases()),
            ),
        ]);
    }

    #[Route('/exports', name: 'app_admin_exports')]
    public function exports(CompanyRepository $companies): Response
    {
        return $this->render('admin/exports.html.twig', [
            'companies' => $companies->createQueryBuilder('c')->orderBy('c.name', 'ASC')->getQuery()->getResult(),
        ]);
    }

    #[Route('/exports/orders.csv', name: 'app_admin_exports_orders', methods: ['GET'])]
    public function exportOrders(Request $request, OrderRepository $orders, OrderExporter $exporter): StreamedResponse
    {
        $ids = array_filter(array_map('intval', (array) $request->query->all('ids')));
        $status = (string) $request->query->get('status', '');
        $companyId = (int) $request->query->get('company', 0);
        $from = (string) $request->query->get('from', '');
        $to = (string) $request->query->get('to', '');

        $qb = $orders->createQueryBuilder('o')->orderBy('o.createdAt', 'DESC');
        if (!empty($ids)) {
            $qb->andWhere('o.id IN (:ids)')->setParameter('ids', $ids);
        } else {
            if ($status !== '') {
                $qb->andWhere('o.status = :s')->setParameter('s', OrderStatus::from($status));
            }
            if ($companyId > 0) {
                $qb->andWhere('o.company = :c')->setParameter('c', $companyId);
            }
            if ($from !== '') {
                $qb->andWhere('o.createdAt >= :f')->setParameter('f', new \DateTimeImmutable($from));
            }
            if ($to !== '') {
                $qb->andWhere('o.createdAt < :t')->setParameter('t', (new \DateTimeImmutable($to))->modify('+1 day'));
            }
        }

        return $exporter->toCsv($qb->getQuery()->getResult(), sprintf('afdal-commandes-%s.csv', date('Y-m-d')));
    }

    #[Route('/exports/revenue-by-company.csv', name: 'app_admin_exports_revenue', methods: ['GET'])]
    public function exportRevenueByCompany(Request $request, OrderExporter $exporter): StreamedResponse
    {
        $from = $request->query->get('from') ? new \DateTimeImmutable((string) $request->query->get('from')) : new \DateTimeImmutable('first day of this year');
        $to = $request->query->get('to') ? (new \DateTimeImmutable((string) $request->query->get('to')))->modify('+1 day') : new \DateTimeImmutable('tomorrow');
        return $exporter->toCsvRevenueByCompany($from, $to, sprintf('afdal-ca-entreprises-%s.csv', date('Y-m-d')));
    }

    #[Route('/exports/companies.csv', name: 'app_admin_exports_companies', methods: ['GET'])]
    public function exportCompanies(OrderExporter $exporter): StreamedResponse
    {
        return $exporter->toCsvCompanies(sprintf('afdal-entreprises-%s.csv', date('Y-m-d')));
    }
}
