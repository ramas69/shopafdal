<?php

namespace App\Controller\Admin;

use App\Entity\Company;
use App\Entity\CompanyPrice;
use App\Entity\Product;
use App\Repository\CompanyPriceRepository;
use App\Repository\CompanyRepository;
use App\Repository\InvitationRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/entreprises')]
#[IsGranted('ROLE_ADMIN')]
final class CompanyController extends AbstractController
{
    #[Route('', name: 'app_admin_companies')]
    public function list(Request $request, CompanyRepository $companies, EntityManagerInterface $em, InvitationRepository $invitations): Response
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
            'pending_company_ids' => $invitations->pendingCompanyIdMap(),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_company_detail', requirements: ['id' => '\d+'])]
    public function detail(
        Company $company,
        OrderRepository $orders,
        EntityManagerInterface $em,
        CompanyPriceRepository $companyPrices,
        ProductRepository $productsRepo,
        InvitationRepository $invitations,
    ): Response {
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

        $negotiated = $companyPrices->findForCompany($company);
        $allProducts = $productsRepo->createQueryBuilder('p')->orderBy('p.name', 'ASC')->getQuery()->getResult();

        $pendingMap = $invitations->pendingCompanyIdMap();
        $accessStatus = $company->getAccessStatus(isset($pendingMap[$company->getId()]));

        return $this->render('admin/company/detail.html.twig', [
            'company' => $company,
            'recent_orders' => $companyOrders,
            'totals' => [
                'orders' => (int) $totals['orders_count'],
                'revenue' => (int) $totals['revenue'],
                'qty' => (int) $totals['qty'],
            ],
            'negotiated_prices' => $negotiated,
            'all_products' => $allProducts,
            'access_status' => $accessStatus,
        ]);
    }

    #[Route('/{id}/tarif-negocie', name: 'app_admin_company_price_save', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function savePrice(
        Company $company,
        Request $request,
        ProductRepository $productsRepo,
        CompanyPriceRepository $companyPrices,
        EntityManagerInterface $em,
    ): RedirectResponse {
        $productId = (int) $request->request->get('product_id', 0);
        $priceStr = (string) $request->request->get('price', '');
        $product = $productsRepo->find($productId);

        if (!$product) {
            $this->addFlash('error', 'Produit invalide.');
            return $this->redirectToRoute('app_admin_company_detail', ['id' => $company->getId()]);
        }
        if ($priceStr === '' || !is_numeric(str_replace(',', '.', $priceStr))) {
            $this->addFlash('error', 'Prix invalide.');
            return $this->redirectToRoute('app_admin_company_detail', ['id' => $company->getId()]);
        }

        $cents = (int) round(((float) str_replace(',', '.', $priceStr)) * 100);
        $existing = $companyPrices->findForCompanyAndProduct($company, $product);
        if ($existing) {
            $existing->setUnitPriceCents($cents);
        } else {
            $cp = (new CompanyPrice())->setCompany($company)->setProduct($product)->setUnitPriceCents($cents);
            $em->persist($cp);
        }
        $em->flush();
        $this->addFlash('success', sprintf('Tarif négocié pour « %s » : %s', $product->getName(), number_format($cents / 100, 2, ',', ' ') . ' €'));
        return $this->redirectToRoute('app_admin_company_detail', ['id' => $company->getId()]);
    }

    #[Route('/{id}/tarif-negocie/{priceId}/delete', name: 'app_admin_company_price_delete', methods: ['POST'], requirements: ['id' => '\d+', 'priceId' => '\d+'])]
    public function deletePrice(
        Company $company,
        int $priceId,
        CompanyPriceRepository $companyPrices,
        EntityManagerInterface $em,
    ): RedirectResponse {
        $cp = $companyPrices->find($priceId);
        if (!$cp || $cp->getCompany()->getId() !== $company->getId()) {
            throw $this->createNotFoundException();
        }
        $productName = $cp->getProduct()->getName();
        $em->remove($cp);
        $em->flush();
        $this->addFlash('success', sprintf('Tarif négocié supprimé pour « %s ».', $productName));
        return $this->redirectToRoute('app_admin_company_detail', ['id' => $company->getId()]);
    }

    #[Route('/new', name: 'app_admin_company_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger, CompanyRepository $companies): Response
    {
        $name = trim((string) $request->request->get('name', ''));
        $siret = trim((string) $request->request->get('siret', ''));
        $errors = [];

        if ($request->isMethod('POST')) {
            if ($name === '') {
                $errors['name'] = 'Nom requis.';
            } else {
                $slug = strtolower((string) $slugger->slug($name));
                if ($companies->findOneBy(['slug' => $slug])) {
                    $errors['name'] = 'Une entreprise avec ce nom existe déjà.';
                }
            }

            if (empty($errors)) {
                $company = (new Company())->setName($name)->setSlug($slug)->setSiret($siret ?: null);
                $em->persist($company);
                $em->flush();
                $this->addFlash('success', sprintf('« %s » créée. Prépare son catalogue (tarifs, produits) puis envoie l\'invitation.', $company->getName()));
                return $this->redirectToRoute('app_admin_company_detail', ['id' => $company->getId()]);
            }
        }

        $status = $request->isMethod('POST') && !empty($errors) ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;
        return $this->render('admin/company/new.html.twig', [
            'name' => $name,
            'siret' => $siret,
            'errors' => $errors,
        ], new Response(null, $status));
    }

    #[Route('/quick-create', name: 'app_admin_company_quick_create', methods: ['POST'])]
    public function quickCreate(Request $request, EntityManagerInterface $em, SluggerInterface $slugger, CompanyRepository $companies): JsonResponse
    {
        $name = trim((string) $request->request->get('name', ''));
        $siret = trim((string) $request->request->get('siret', '')) ?: null;
        if ($name === '') {
            return new JsonResponse(['error' => 'Nom requis.'], 422);
        }
        $slug = strtolower((string) $slugger->slug($name));
        if ($companies->findOneBy(['slug' => $slug])) {
            return new JsonResponse(['error' => 'Une entreprise avec ce nom existe déjà.'], 422);
        }
        $company = (new Company())->setName($name)->setSlug($slug)->setSiret($siret);
        $em->persist($company);
        $em->flush();
        $status = $company->getAccessStatus(false);
        return new JsonResponse([
            'id' => $company->getId(),
            'name' => $company->getName(),
            'siret' => $company->getSiret(),
            'users_count' => 0,
            'status_key' => $status['key'],
            'status_label' => $status['label'],
            'invite_url' => $this->generateUrl('app_admin_invitation_new', ['company' => $company->getId()]),
        ]);
    }
}
