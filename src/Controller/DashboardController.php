<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\OrderStatus;
use App\Enum\ProductStatus;
use App\Repository\CompanyRepository;
use App\Repository\MarkingAssetRepository;
use App\Repository\OrderMessageRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductVariantRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class DashboardController extends AbstractController
{
    private const SQL_DATE_FORMAT = 'Y-m-d H:i:s';
    private const COUNT_ORDERS = 'COUNT(o.id)';
    private const WHERE_STATUS = 'o.status = :status';
    private const WHERE_PLACED_BEFORE = 'o.placedAt < :cutoff';

    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->redirectToRoute($user->isAdmin() ? 'app_admin' : 'app_catalogue');
    }

    #[Route('/admin', name: 'app_admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function admin(
        CompanyRepository $companies,
        ProductRepository $products,
        OrderRepository $orders,
        MarkingAssetRepository $markings,
        OrderMessageRepository $messages,
        ProductVariantRepository $variants,
        EntityManagerInterface $em,
    ): Response {
        $now = new \DateTimeImmutable();
        $conn = $em->getConnection();

        return $this->render('dashboard/admin_stub.html.twig', [
            'today' => $this->computeToday($orders, $now),
            'month' => $this->computeMonth($conn, $now),
            'daily_chart' => $this->computeDailyChart($conn, $now),
            'max_daily' => $this->maxDaily($this->computeDailyChart($conn, $now)),
            'urgent_orders' => $this->urgentOrders($orders, $now, 5),
            'recent_orders' => $orders->createQueryBuilder('o')->orderBy('o.createdAt', 'DESC')->setMaxResults(8)->getQuery()->getResult(),
            'top_clients' => $this->topClients($conn, $now),
            'stuck_orders' => $this->stuckOrders($orders, $now),
            'inactive_companies' => $this->inactiveCompanies($conn, $now),
            'pending_bats' => $markings->findPendingForReview(8),
            'pending_bats_count' => $markings->countPendingForReview(),
            'rejected_bats' => $this->rejectedBatsWaitingReupload($conn, $now),
            'rejected_bats_count' => $this->rejectedBatsWaitingReuploadCount($conn, $now),
            'unread_messages_count' => $messages->countUnreadForAdmin(),
            'unread_threads' => $this->unreadMessageThreads($conn),
            'late_deliveries' => $this->lateDeliveries($conn, $now),
            'late_deliveries_count' => $this->lateDeliveriesCount($conn, $now),
            'low_stock_variants' => $this->lowStockVariants($conn),
            'low_stock_count' => $this->lowStockCount($conn),
            'totals' => [
                'companies' => $companies->count([]),
                'products' => $products->count(['status' => ProductStatus::PUBLISHED]),
            ],
        ]);
    }

    /** @return array<int, array<string,mixed>> BAT rejetés dont aucune version ultérieure n'a été uploadée, > 7 jours */
    private function rejectedBatsWaitingReupload(Connection $conn, \DateTimeImmutable $now): array
    {
        $cutoff = $now->modify('-7 days');
        return $conn->fetchAllAssociative(
            "SELECT m.id, m.version, m.reviewed_at, m.feedback, o.reference, c.name AS company_name
             FROM marking_assets m
             JOIN order_items oi ON oi.id = m.order_item_id
             JOIN orders o ON o.id = oi.order_id
             JOIN companies c ON c.id = o.company_id
             WHERE m.status = 'rejected'
               AND m.reviewed_at < :cutoff
               AND NOT EXISTS (
                   SELECT 1 FROM marking_assets m2
                   WHERE m2.order_item_id = m.order_item_id AND m2.version > m.version
               )
             ORDER BY m.reviewed_at ASC
             LIMIT 5",
            ['cutoff' => $cutoff->format(self::SQL_DATE_FORMAT)]
        );
    }

    private function rejectedBatsWaitingReuploadCount(Connection $conn, \DateTimeImmutable $now): int
    {
        $cutoff = $now->modify('-7 days');
        return (int) $conn->fetchOne(
            "SELECT COUNT(*)
             FROM marking_assets m
             WHERE m.status = 'rejected'
               AND m.reviewed_at < :cutoff
               AND NOT EXISTS (
                   SELECT 1 FROM marking_assets m2
                   WHERE m2.order_item_id = m.order_item_id AND m2.version > m.version
               )",
            ['cutoff' => $cutoff->format(self::SQL_DATE_FORMAT)]
        );
    }

    /** @return array<int, array<string,mixed>> Derniers threads avec messages admin non lus */
    private function unreadMessageThreads(Connection $conn): array
    {
        return $conn->fetchAllAssociative(
            "SELECT o.reference, c.name AS company_name,
                    COUNT(m.id) AS unread_count,
                    MAX(m.created_at) AS last_message_at
             FROM order_messages m
             JOIN orders o ON o.id = m.order_id
             JOIN companies c ON c.id = o.company_id
             WHERE m.read_by_admin_at IS NULL
             GROUP BY o.id, o.reference, c.name
             ORDER BY last_message_at DESC
             LIMIT 5"
        );
    }

    /** @return array<int, array<string,mixed>> Commandes dont ETA est passée et non livrées */
    private function lateDeliveries(Connection $conn, \DateTimeImmutable $now): array
    {
        return $conn->fetchAllAssociative(
            "SELECT o.reference, o.status, o.estimated_delivery_at, o.carrier, o.tracking_number,
                    c.name AS company_name,
                    DATEDIFF(:now, o.estimated_delivery_at) AS days_late
             FROM orders o
             JOIN companies c ON c.id = o.company_id
             WHERE o.estimated_delivery_at IS NOT NULL
               AND o.estimated_delivery_at < :now
               AND o.status NOT IN ('delivered', 'cancelled')
             ORDER BY o.estimated_delivery_at ASC
             LIMIT 5",
            ['now' => $now->format(self::SQL_DATE_FORMAT)]
        );
    }

    private function lateDeliveriesCount(Connection $conn, \DateTimeImmutable $now): int
    {
        return (int) $conn->fetchOne(
            "SELECT COUNT(*)
             FROM orders o
             WHERE o.estimated_delivery_at IS NOT NULL
               AND o.estimated_delivery_at < :now
               AND o.status NOT IN ('delivered', 'cancelled')",
            ['now' => $now->format(self::SQL_DATE_FORMAT)]
        );
    }

    /** @return array<int, array<string,mixed>> Variantes de produits publiés avec stock ≤ 5 */
    private function lowStockVariants(Connection $conn): array
    {
        return $conn->fetchAllAssociative(
            "SELECT v.id, v.stock, v.size, v.color, v.sku, p.id AS product_id, p.name AS product_name
             FROM product_variants v
             JOIN products p ON p.id = v.product_id
             WHERE p.status = 'published'
               AND v.stock IS NOT NULL
               AND v.stock <= 5
             ORDER BY v.stock ASC, p.name ASC
             LIMIT 6"
        );
    }

    private function lowStockCount(Connection $conn): int
    {
        return (int) $conn->fetchOne(
            "SELECT COUNT(*)
             FROM product_variants v
             JOIN products p ON p.id = v.product_id
             WHERE p.status = 'published'
               AND v.stock IS NOT NULL
               AND v.stock <= 5"
        );
    }

    /** @return array<string,int> */
    private function computeToday(OrderRepository $orders, \DateTimeImmutable $now): array
    {
        $startToday = $now->setTime(0, 0);
        $urgentCutoff = $now->modify('-24 hours');

        return [
            'new' => $this->countOrders($orders, fn($qb) => $qb
                ->andWhere('o.createdAt >= :start')->setParameter('start', $startToday)),
            'to_confirm' => $this->countOrders($orders, fn($qb) => $qb
                ->andWhere(self::WHERE_STATUS)->setParameter('status', OrderStatus::PLACED)),
            'to_ship' => $this->countOrders($orders, fn($qb) => $qb
                ->andWhere('o.status IN (:statuses)')
                ->setParameter('statuses', [OrderStatus::CONFIRMED, OrderStatus::IN_PRODUCTION])),
            'urgent' => $this->countOrders($orders, fn($qb) => $qb
                ->andWhere(self::WHERE_STATUS)->setParameter('status', OrderStatus::PLACED)
                ->andWhere(self::WHERE_PLACED_BEFORE)->setParameter('cutoff', $urgentCutoff)),
        ];
    }

    private function countOrders(OrderRepository $orders, callable $apply): int
    {
        $qb = $orders->createQueryBuilder('o')->select(self::COUNT_ORDERS);
        $apply($qb);
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** @return array<string,int|float|null> */
    private function computeMonth(Connection $conn, \DateTimeImmutable $now): array
    {
        $startMonth = $now->modify('first day of this month')->setTime(0, 0);
        $startPrev = $now->modify('first day of -1 month')->setTime(0, 0);

        $current = $this->monthRevenue($conn, $startMonth, null);
        $previous = $this->monthRevenue($conn, $startPrev, $startMonth);

        $growth = $previous['revenue'] > 0
            ? round((($current['revenue'] - $previous['revenue']) / $previous['revenue']) * 100, 1)
            : null;

        return [
            'revenue_cents' => $current['revenue'],
            'orders' => $current['orders_count'],
            'growth' => $growth,
        ];
    }

    /** @return array{revenue:int,orders_count:int} */
    private function monthRevenue(Connection $conn, \DateTimeImmutable $start, ?\DateTimeImmutable $end): array
    {
        $sql = 'SELECT COALESCE(SUM(oi.unit_price_cents * oi.quantity), 0) AS revenue,
                       COUNT(DISTINCT o.id) AS orders_count
                FROM orders o
                JOIN order_items oi ON oi.order_id = o.id
                WHERE o.created_at >= :start AND o.status != :cancelled';
        $params = ['start' => $start->format(self::SQL_DATE_FORMAT), 'cancelled' => 'cancelled'];
        if ($end) {
            $sql .= ' AND o.created_at < :end';
            $params['end'] = $end->format(self::SQL_DATE_FORMAT);
        }
        $row = $conn->fetchAssociative($sql, $params);
        return ['revenue' => (int) $row['revenue'], 'orders_count' => (int) $row['orders_count']];
    }

    /** @return array<int, array{day:string, value:int}> */
    private function computeDailyChart(Connection $conn, \DateTimeImmutable $now): array
    {
        $start = $now->modify('-29 days')->setTime(0, 0);
        $rows = $conn->fetchAllAssociative(
            'SELECT DATE(o.created_at) AS day,
                    COALESCE(SUM(oi.unit_price_cents * oi.quantity), 0) AS revenue
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             WHERE o.created_at >= :start AND o.status != :cancelled
             GROUP BY day ORDER BY day ASC',
            ['start' => $start->format(self::SQL_DATE_FORMAT), 'cancelled' => 'cancelled']
        );
        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r['day']] = (int) $r['revenue'];
        }
        $chart = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = $now->modify("-$i days")->format('Y-m-d');
            $chart[] = ['day' => $day, 'value' => $map[$day] ?? 0];
        }
        return $chart;
    }

    /** @param array<int, array{day:string,value:int}> $chart */
    private function maxDaily(array $chart): int
    {
        return max(array_map(fn($c) => $c['value'], $chart)) ?: 1;
    }

    /** @return \App\Entity\Order[] */
    private function urgentOrders(OrderRepository $orders, \DateTimeImmutable $now, int $limit): array
    {
        return $orders->createQueryBuilder('o')
            ->andWhere(self::WHERE_STATUS)->setParameter('status', OrderStatus::PLACED)
            ->andWhere(self::WHERE_PLACED_BEFORE)->setParameter('cutoff', $now->modify('-24 hours'))
            ->orderBy('o.placedAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    /** @return array<int, array<string,mixed>> */
    private function topClients(Connection $conn, \DateTimeImmutable $now): array
    {
        $start = $now->modify('first day of this month')->setTime(0, 0);
        return $conn->fetchAllAssociative(
            'SELECT c.id, c.name, COUNT(DISTINCT o.id) AS orders_count,
                    COALESCE(SUM(oi.unit_price_cents * oi.quantity), 0) AS revenue
             FROM companies c
             JOIN orders o ON o.company_id = c.id
             JOIN order_items oi ON oi.order_id = o.id
             WHERE o.created_at >= :start AND o.status != :cancelled
             GROUP BY c.id, c.name
             ORDER BY revenue DESC LIMIT 5',
            ['start' => $start->format(self::SQL_DATE_FORMAT), 'cancelled' => 'cancelled']
        );
    }

    /** @return \App\Entity\Order[] */
    private function stuckOrders(OrderRepository $orders, \DateTimeImmutable $now): array
    {
        return $orders->createQueryBuilder('o')
            ->andWhere(self::WHERE_STATUS)->setParameter('status', OrderStatus::PLACED)
            ->andWhere(self::WHERE_PLACED_BEFORE)->setParameter('cutoff', $now->modify('-48 hours'))
            ->orderBy('o.placedAt', 'ASC')
            ->getQuery()->getResult();
    }

    /** @return array<int, array<string,mixed>> */
    private function inactiveCompanies(Connection $conn, \DateTimeImmutable $now): array
    {
        return $conn->fetchAllAssociative(
            'SELECT c.id, c.name, MAX(o.created_at) AS last_order
             FROM companies c
             LEFT JOIN orders o ON o.company_id = c.id
             GROUP BY c.id, c.name
             HAVING MAX(o.created_at) IS NULL OR MAX(o.created_at) < :cutoff
             ORDER BY last_order IS NULL, last_order ASC
             LIMIT 5',
            ['cutoff' => $now->modify('-60 days')->format(self::SQL_DATE_FORMAT)]
        );
    }
}
