<?php

namespace App\Controller\Admin;

use App\Entity\Company;
use App\Repository\CompanyRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/entreprises')]
#[IsGranted('ROLE_ADMIN')]
final class CompanyController extends AbstractController
{
    #[Route('', name: 'app_admin_companies')]
    public function list(Request $request, CompanyRepository $companies, EntityManagerInterface $em): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $qb = $companies->createQueryBuilder('c')->orderBy('c.name', 'ASC');
        if ($q !== '') {
            $qb->andWhere('LOWER(c.name) LIKE :q OR c.siret LIKE :siret')
                ->setParameter('q', '%' . strtolower($q) . '%')
                ->setParameter('siret', '%' . $q . '%');
        }
        $list = $qb->getQuery()->getResult();

        // Enrich each company with aggregated stats via SQL
        $conn = $em->getConnection();
        $stats = $conn->fetchAllKeyValue(
            'SELECT c.id, COALESCE(SUM(oi.unit_price_cents * oi.quantity), 0) AS revenue
             FROM companies c
             LEFT JOIN orders o ON o.company_id = c.id AND o.status != :cancelled
             LEFT JOIN order_items oi ON oi.order_id = o.id
             GROUP BY c.id',
            ['cancelled' => 'cancelled']
        );
        $ordersCount = $conn->fetchAllKeyValue(
            'SELECT company_id, COUNT(*) FROM orders GROUP BY company_id'
        );

        return $this->render('admin/company/list.html.twig', [
            'companies' => $list,
            'revenues' => $stats,
            'orders_count' => $ordersCount,
            'q' => $q,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_company_detail', requirements: ['id' => '\d+'])]
    public function detail(Company $company, OrderRepository $orders, EntityManagerInterface $em): Response
    {
        $companyOrders = $orders->findBy(['company' => $company], ['createdAt' => 'DESC'], 10);

        $totals = $em->getConnection()->fetchAssociative(
            'SELECT COUNT(DISTINCT o.id) AS orders_count,
                    COALESCE(SUM(oi.unit_price_cents * oi.quantity), 0) AS revenue,
                    COALESCE(SUM(oi.quantity), 0) AS qty
             FROM orders o
             LEFT JOIN order_items oi ON oi.order_id = o.id
             WHERE o.company_id = :id AND o.status != :cancelled',
            ['id' => $company->getId(), 'cancelled' => 'cancelled']
        );

        return $this->render('admin/company/detail.html.twig', [
            'company' => $company,
            'recent_orders' => $companyOrders,
            'totals' => [
                'orders' => (int) $totals['orders_count'],
                'revenue' => (int) $totals['revenue'],
                'qty' => (int) $totals['qty'],
            ],
        ]);
    }
}
