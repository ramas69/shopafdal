<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\OrderStatus;
use App\Enum\ProductStatus;
use App\Repository\CompanyRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
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
    private const WHERE_STATUS = self::WHERE_STATUS;
    private const WHERE_PLACED_BEFORE = self::WHERE_PLACED_BEFORE;

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
            'pipeline' => $this->computePipeline($conn),
            'recent_orders' => $orders->createQueryBuilder('o')->orderBy('o.createdAt', 'DESC')->setMaxResults(8)->getQuery()->getResult(),
            'top_clients' => $this->topClients($conn, $now),
            'stuck_orders' => $this->stuckOrders($orders, $now),
            'inactive_companies' => $this->inactiveCompanies($conn, $now),
            'totals' => [
                'companies' => $companies->count([]),
                'products' => $products->count(['status' => ProductStatus::PUBLISHED]),
            ],
        ]);
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

    /** @return array<int, array{status:OrderStatus,count:int}> */
    private function computePipeline(Connection $conn): array
    {
        $rows = $conn->fetchAllAssociative(
            "SELECT status, COUNT(*) AS count FROM orders
             WHERE status NOT IN ('delivered', 'cancelled')
             GROUP BY status"
        );
        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r['status']] = (int) $r['count'];
        }
        return [
            ['status' => OrderStatus::PLACED, 'count' => $map['placed'] ?? 0],
            ['status' => OrderStatus::CONFIRMED, 'count' => $map['confirmed'] ?? 0],
            ['status' => OrderStatus::IN_PRODUCTION, 'count' => $map['in_production'] ?? 0],
            ['status' => OrderStatus::SHIPPED, 'count' => $map['shipped'] ?? 0],
        ];
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
             ORDER BY last_order ASC NULLS LAST
             LIMIT 5',
            ['cutoff' => $now->modify('-60 days')->format(self::SQL_DATE_FORMAT)]
        );
    }
}
