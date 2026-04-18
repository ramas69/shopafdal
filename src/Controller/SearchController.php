<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\ProductStatus;
use App\Repository\AntennaRepository;
use App\Repository\CompanyRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/recherche')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class SearchController extends AbstractController
{
    private const MAX_PER_GROUP = 5;

    #[Route('', name: 'app_search', methods: ['GET'])]
    public function search(
        Request $request,
        OrderRepository $orders,
        ProductRepository $products,
        CompanyRepository $companies,
        AntennaRepository $antennas,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        $q = trim((string) $request->query->get('q', ''));

        if (strlen($q) < 2) {
            return $this->json(['groups' => []]);
        }

        $like = '%' . strtolower($q) . '%';
        $groups = [];

        // Orders (scoped: admin = all, client = own company)
        $orderQb = $orders->createQueryBuilder('o')
            ->andWhere('LOWER(o.reference) LIKE :q')
            ->setParameter('q', $like)
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults(self::MAX_PER_GROUP);
        if (!$user->isAdmin()) {
            $orderQb->andWhere('o.company = :company')->setParameter('company', $user->getCompany());
        }
        $orderResults = $orderQb->getQuery()->getResult();
        if (!empty($orderResults)) {
            $groups[] = [
                'label' => 'Commandes',
                'items' => array_map(fn($o) => [
                    'title' => $o->getReference(),
                    'subtitle' => $o->getCompany()->getName() . ' · ' . $o->getStatus()->label(),
                    'url' => $user->isAdmin()
                        ? $this->generateUrl('app_admin_order_detail', ['reference' => $o->getReference()])
                        : $this->generateUrl('app_order_detail', ['reference' => $o->getReference()]),
                    'icon' => 'list',
                ], $orderResults),
            ];
        }

        // Products (admin: all; client: published only)
        $productQb = $products->createQueryBuilder('p')
            ->andWhere('LOWER(p.name) LIKE :q OR LOWER(p.slug) LIKE :q')
            ->setParameter('q', $like)
            ->orderBy('p.name', 'ASC')
            ->setMaxResults(self::MAX_PER_GROUP);
        if (!$user->isAdmin()) {
            $productQb->andWhere('p.status = :published')->setParameter('published', ProductStatus::PUBLISHED);
        }
        $productResults = $productQb->getQuery()->getResult();
        if (!empty($productResults)) {
            $groups[] = [
                'label' => 'Produits',
                'items' => array_map(fn($p) => [
                    'title' => $p->getName(),
                    'subtitle' => ($p->getCategory() ?? '—') . ' · ' . (($p->getBasePriceCents() / 100) . ' €'),
                    'url' => $user->isAdmin()
                        ? $this->generateUrl('app_admin_product_detail', ['id' => $p->getId()])
                        : $this->generateUrl('app_catalogue_detail', ['slug' => $p->getSlug()]),
                    'icon' => 'package',
                ], $productResults),
            ];
        }

        // Companies (admin only)
        if ($user->isAdmin()) {
            $companyResults = $companies->createQueryBuilder('c')
                ->andWhere('LOWER(c.name) LIKE :q')
                ->setParameter('q', $like)
                ->orderBy('c.name', 'ASC')
                ->setMaxResults(self::MAX_PER_GROUP)
                ->getQuery()->getResult();
            if (!empty($companyResults)) {
                $groups[] = [
                    'label' => 'Clients',
                    'items' => array_map(fn($c) => [
                        'title' => $c->getName(),
                        'subtitle' => $c->getAntennas()->count() . ' antenne(s)',
                        'url' => '#',
                        'icon' => 'users',
                    ], $companyResults),
                ];
            }
        }

        // Antennas (admin: all; client: own company)
        $antennaQb = $antennas->createQueryBuilder('a')
            ->andWhere('LOWER(a.name) LIKE :q OR LOWER(a.city) LIKE :q')
            ->setParameter('q', $like)
            ->orderBy('a.name', 'ASC')
            ->setMaxResults(self::MAX_PER_GROUP);
        if (!$user->isAdmin()) {
            $antennaQb->andWhere('a.company = :company')->setParameter('company', $user->getCompany());
        }
        $antennaResults = $antennaQb->getQuery()->getResult();
        if (!empty($antennaResults)) {
            $groups[] = [
                'label' => 'Antennes',
                'items' => array_map(fn($a) => [
                    'title' => $a->getName(),
                    'subtitle' => $a->getPostalCode() . ' ' . $a->getCity() . ($user->isAdmin() ? ' · ' . $a->getCompany()->getName() : ''),
                    'url' => $user->isAdmin() ? '#' : $this->generateUrl('app_antenna_detail', ['id' => $a->getId()]),
                    'icon' => 'pin',
                ], $antennaResults),
            ];
        }

        return $this->json(['groups' => $groups]);
    }
}
