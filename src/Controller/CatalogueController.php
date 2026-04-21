<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\User;
use App\Repository\CompanyPriceRepository;
use App\Repository\FavoriteRepository;
use App\Repository\ProductRepository;
use App\Service\Cart;
use App\Service\PricingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CLIENT_MANAGER')]
final class CatalogueController extends AbstractController
{
    private const SIZE_ORDER = ['TU', 'XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
    private const MARKING_UPLOAD_PREFIX = '/uploads/markings/';
    private const MARKING_ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml', 'application/pdf'];

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads/markings')]
        private string $markingUploadDir,
    ) {}

    #[Route('/catalogue', name: 'app_catalogue')]
    public function list(Request $request, ProductRepository $products, FavoriteRepository $favorites): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $favoritedIds = $favorites->findProductIdsForUser($user);
        $search = trim((string) $request->query->get('q', ''));
        $category = (string) $request->query->get('category', '');
        $colors = array_filter(array_map('trim', explode(',', (string) $request->query->get('colors', ''))));
        $sizes = array_filter(array_map('trim', explode(',', (string) $request->query->get('sizes', ''))));

        $qb = $products->createCatalogueQueryBuilder($user->getCompany())
            ->orderBy('p.name', 'ASC');

        if ($search !== '') {
            $qb->andWhere('LOWER(p.name) LIKE :search OR LOWER(p.description) LIKE :search')
                ->setParameter('search', '%' . strtolower($search) . '%');
        }
        if ($category !== '') {
            $qb->andWhere('p.category = :cat')->setParameter('cat', $category);
        }
        if (!empty($colors) || !empty($sizes)) {
            $qb->innerJoin('p.variants', 'fv');
            if (!empty($colors)) {
                $qb->andWhere('fv.color IN (:colors)')->setParameter('colors', array_values($colors));
            }
            if (!empty($sizes)) {
                $qb->andWhere('fv.size IN (:sizes)')->setParameter('sizes', array_values($sizes));
            }
            $qb->groupBy('p.id');
        }

        $results = $qb->getQuery()->getResult();

        $categories = $products->createCatalogueQueryBuilder($user->getCompany())
            ->select('DISTINCT p.category')
            ->andWhere('p.category IS NOT NULL')
            ->orderBy('p.category', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        // Facets : toutes les couleurs + tailles disponibles sur produits publiés accessibles
        $variantRows = $products->createCatalogueQueryBuilder($user->getCompany())
            ->select('DISTINCT v.color AS color, v.colorHex AS hex, v.size AS size')
            ->innerJoin('p.variants', 'v')
            ->getQuery()
            ->getArrayResult();

        $colorFacets = [];
        $sizeFacets = [];
        foreach ($variantRows as $row) {
            $c = $row['color'] ?? null;
            $s = $row['size'] ?? null;
            if ($c !== null && !isset($colorFacets[$c])) {
                $colorFacets[$c] = ['name' => $c, 'hex' => $row['hex'] ?? null];
            }
            if ($s !== null) {
                $sizeFacets[$s] = true;
            }
        }
        ksort($colorFacets);
        $sizeFacets = array_keys($sizeFacets);
        usort($sizeFacets, function ($a, $b) {
            $ia = array_search($a, self::SIZE_ORDER, true);
            $ib = array_search($b, self::SIZE_ORDER, true);
            return ($ia === false ? 999 : $ia) - ($ib === false ? 999 : $ib);
        });

        return $this->render('catalogue/list.html.twig', [
            'products' => $results,
            'categories' => $categories,
            'q' => $search,
            'current_category' => $category,
            'color_facets' => array_values($colorFacets),
            'size_facets' => $sizeFacets,
            'current_colors' => array_values($colors),
            'current_sizes' => array_values($sizes),
            'favorited_ids' => $favoritedIds,
        ]);
    }

    #[Route('/catalogue/{slug}', name: 'app_catalogue_detail')]
    public function detail(
        Product $product,
        FavoriteRepository $favorites,
        PricingService $pricing,
        CompanyPriceRepository $companyPrices,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $isFavorited = $favorites->findOneByUserAndProduct($user, $product) !== null;
        $tiers = $pricing->publicTiers($product);
        $negotiated = $user->getCompany() ? $companyPrices->findForCompanyAndProduct($user->getCompany(), $product) : null;
        if (!$product->isPublished() || !$user->getCompany() || !$product->isAllowedFor($user->getCompany())) {
            throw $this->createNotFoundException();
        }

        $variantsByColor = [];
        $sizesSeen = [];
        foreach ($product->getVariants() as $v) {
            $variantsByColor[$v->getColor()] ??= [
                'color' => $v->getColor(),
                'hex' => $v->getColorHex(),
                'sizes' => [],
            ];
            $variantsByColor[$v->getColor()]['sizes'][$v->getSize()] = [
                'id' => $v->getId(),
                'sku' => $v->getSku(),
                'stock' => $v->getStock(),
                'out' => $v->isOutOfStock(),
                'low' => $v->isLowStock(),
            ];
            $sizesSeen[$v->getSize()] = true;
        }
        $variantsByColor = array_values($variantsByColor);

        $allSizes = array_keys($sizesSeen);
        usort($allSizes, function ($a, $b) {
            $ia = array_search($a, self::SIZE_ORDER, true);
            $ib = array_search($b, self::SIZE_ORDER, true);
            return ($ia === false ? 999 : $ia) - ($ib === false ? 999 : $ib);
        });

        return $this->render('catalogue/detail.html.twig', [
            'product' => $product,
            'variants_by_color' => $variantsByColor,
            'all_sizes' => $allSizes,
            'is_favorited' => $isFavorited,
            'price_tiers' => $tiers,
            'negotiated_price_cents' => $negotiated?->getUnitPriceCents(),
        ]);
    }

    #[Route('/catalogue/{slug}/add', name: 'app_catalogue_add', methods: ['POST'])]
    public function addToCart(Product $product, Request $request, Cart $cart): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$product->isPublished() || !$user->getCompany() || !$product->isAllowedFor($user->getCompany())) {
            throw $this->createNotFoundException();
        }
        $quantities = $request->request->all('quantities');
        $marking = null;

        $markingZone = trim((string) $request->request->get('marking_zone', ''));
        if ($markingZone !== '') {
            $marking = [
                'zone' => $markingZone,
                'size' => (string) $request->request->get('marking_size', 'A4'),
            ];

            /** @var UploadedFile|null $logo */
            $logo = $request->files->get('marking_logo');
            if ($logo) {
                if (!$logo->isValid()) {
                    $this->addFlash('error', sprintf('Logo rejeté (taille max %s).', ini_get('upload_max_filesize') ?: '?'));
                    return $this->redirectToRoute('app_catalogue_detail', ['slug' => $product->getSlug()]);
                }
                $mime = $logo->getMimeType();
                if (!$mime || !in_array($mime, self::MARKING_ALLOWED_MIME, true)) {
                    $this->addFlash('error', sprintf('Logo : format non supporté (%s).', $mime ?: 'inconnu'));
                    return $this->redirectToRoute('app_catalogue_detail', ['slug' => $product->getSlug()]);
                }
                $filename = bin2hex(random_bytes(12)) . '.' . ($logo->guessExtension() ?: 'bin');
                try {
                    $logo->move($this->markingUploadDir, $filename);
                    $marking['logo_path'] = self::MARKING_UPLOAD_PREFIX . $filename;
                } catch (FileException) {
                    $this->addFlash('error', 'Échec du téléversement du logo.');
                    return $this->redirectToRoute('app_catalogue_detail', ['slug' => $product->getSlug()]);
                }
            }
        }

        $added = 0;
        foreach ($quantities as $variantId => $qty) {
            $qty = (int) $qty;
            if ($qty < 1) {
                continue;
            }
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
