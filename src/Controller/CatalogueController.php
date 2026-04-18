<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\Cart;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CLIENT_MANAGER')]
final class CatalogueController extends AbstractController
{
    #[Route('/catalogue', name: 'app_catalogue')]
    public function list(Request $request, ProductRepository $products): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $category = (string) $request->query->get('category', '');

        $qb = $products->createQueryBuilder('p')
            ->andWhere('p.active = true')
            ->orderBy('p.name', 'ASC');

        if ($search !== '') {
            $qb->andWhere('LOWER(p.name) LIKE :search OR LOWER(p.description) LIKE :search')
                ->setParameter('search', '%' . strtolower($search) . '%');
        }
        if ($category !== '') {
            $qb->andWhere('p.category = :cat')->setParameter('cat', $category);
        }

        $results = $qb->getQuery()->getResult();

        $categories = $products->createQueryBuilder('p')
            ->select('DISTINCT p.category')
            ->andWhere('p.active = true')
            ->andWhere('p.category IS NOT NULL')
            ->orderBy('p.category', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return $this->render('catalogue/list.html.twig', [
            'products' => $results,
            'categories' => $categories,
            'q' => $search,
            'current_category' => $category,
        ]);
    }

    #[Route('/catalogue/{slug}', name: 'app_catalogue_detail')]
    public function detail(Product $product): Response
    {
        if (!$product->isActive()) {
            throw $this->createNotFoundException();
        }

        $variantsByColor = [];
        foreach ($product->getVariants() as $v) {
            $variantsByColor[$v->getColor()] ??= [
                'color' => $v->getColor(),
                'hex' => $v->getColorHex(),
                'sizes' => [],
            ];
            $variantsByColor[$v->getColor()]['sizes'][$v->getSize()] = [
                'id' => $v->getId(),
                'sku' => $v->getSku(),
            ];
        }
        $variantsByColor = array_values($variantsByColor);

        return $this->render('catalogue/detail.html.twig', [
            'product' => $product,
            'variants_by_color' => $variantsByColor,
        ]);
    }

    #[Route('/catalogue/{slug}/add', name: 'app_catalogue_add', methods: ['POST'])]
    public function addToCart(Product $product, Request $request, Cart $cart): RedirectResponse
    {
        $quantities = $request->request->all('quantities');
        $marking = null;

        $markingZone = trim((string) $request->request->get('marking_zone', ''));
        if ($markingZone !== '') {
            $marking = [
                'zone' => $markingZone,
                'size' => (string) $request->request->get('marking_size', 'A4'),
            ];
        }

        $added = 0;
        foreach ($quantities as $variantId => $qty) {
            $qty = (int) $qty;
            if ($qty < 1) continue;
            $cart->add((int) $variantId, $qty, $marking);
            $added += $qty;
        }

        if ($added === 0) {
            $this->addFlash('error', 'Indiquez au moins une quantité pour ajouter au panier.');
            return $this->redirectToRoute('app_catalogue_detail', ['slug' => $product->getSlug()]);
        }

        $this->addFlash('success', sprintf('%d article(s) ajouté(s) au panier.', $added));
        return $this->redirectToRoute('app_cart');
    }
}
