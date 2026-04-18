<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Enum\ProductStatus;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/produits')]
#[IsGranted('ROLE_ADMIN')]
final class ProductController extends AbstractController
{
    private const UPLOAD_PUBLIC_PREFIX = '/uploads/products/';
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads/products')]
        private string $uploadDir,
    ) {}
    #[Route('', name: 'app_admin_products')]
    public function list(Request $request, ProductRepository $products): Response
    {
        $status = (string) $request->query->get('status', '');
        $search = trim((string) $request->query->get('q', ''));

        $qb = $products->createQueryBuilder('p')->orderBy('p.createdAt', 'DESC');
        if ($status !== '') {
            $qb->andWhere('p.status = :status')->setParameter('status', ProductStatus::from($status));
        }
        if ($search !== '') {
            $qb->andWhere('LOWER(p.name) LIKE :q')->setParameter('q', '%' . strtolower($search) . '%');
        }

        return $this->render('admin/product/list.html.twig', [
            'products' => $qb->getQuery()->getResult(),
            'statuses' => ProductStatus::cases(),
            'current_status' => $status,
            'q' => $search,
            'counts' => [
                'draft' => $products->count(['status' => ProductStatus::DRAFT]),
                'published' => $products->count(['status' => ProductStatus::PUBLISHED]),
            ],
        ]);
    }

    #[Route('/new', name: 'app_admin_product_new')]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        return $this->handleForm(new Product(), $request, $em, $slugger);
    }

    #[Route('/{id}', name: 'app_admin_product_detail', requirements: ['id' => '\d+'])]
    public function detail(Product $product): Response
    {
        $variantsByColor = [];
        foreach ($product->getVariants() as $v) {
            $variantsByColor[$v->getColor()] ??= ['color' => $v->getColor(), 'hex' => $v->getColorHex(), 'sizes' => []];
            $variantsByColor[$v->getColor()]['sizes'][] = ['size' => $v->getSize(), 'sku' => $v->getSku()];
        }
        return $this->render('admin/product/detail.html.twig', [
            'product' => $product,
            'variants_by_color' => array_values($variantsByColor),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_product_edit', requirements: ['id' => '\d+'])]
    public function edit(Product $product, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        return $this->handleForm($product, $request, $em, $slugger);
    }

    #[Route('/{id}/publish', name: 'app_admin_product_publish', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function publish(Product $product, EntityManagerInterface $em): RedirectResponse
    {
        if ($product->getVariants()->isEmpty()) {
            $this->addFlash('error', 'Impossible de publier : ajoutez au moins une variante.');
            return $this->redirectToRoute('app_admin_product_edit', ['id' => $product->getId()]);
        }
        $product->publish();
        $em->flush();
        $this->addFlash('success', sprintf('« %s » publié et visible par les clients.', $product->getName()));
        return $this->redirectToRoute('app_admin_product_detail', ['id' => $product->getId()]);
    }

    #[Route('/{id}/unpublish', name: 'app_admin_product_unpublish', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function unpublish(Product $product, EntityManagerInterface $em): RedirectResponse
    {
        $product->unpublish();
        $em->flush();
        $this->addFlash('success', sprintf('« %s » repassé en brouillon. Plus visible par les clients.', $product->getName()));
        return $this->redirectToRoute('app_admin_product_detail', ['id' => $product->getId()]);
    }

    #[Route('/{id}/delete', name: 'app_admin_product_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Product $product, EntityManagerInterface $em): RedirectResponse
    {
        if ($product->isPublished()) {
            $this->addFlash('error', 'Dépubliez le produit avant de le supprimer.');
            return $this->redirectToRoute('app_admin_product_edit', ['id' => $product->getId()]);
        }
        $em->remove($product);
        $em->flush();
        $this->addFlash('success', 'Produit supprimé.');
        return $this->redirectToRoute('app_admin_products');
    }

    private function handleForm(Product $product, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $isNew = $product->getId() === null;
        $errors = [];

        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name', ''));
            $description = trim((string) $request->request->get('description', ''));
            $category = trim((string) $request->request->get('category', ''));
            $material = trim((string) $request->request->get('material', ''));
            $priceEuros = (string) $request->request->get('base_price', '');

            if ($name === '') {
                $errors['name'] = 'Nom requis.';
            }
            if ($priceEuros === '' || !is_numeric(str_replace(',', '.', $priceEuros))) {
                $errors['base_price'] = 'Prix HT invalide.';
            }

            $variantsInput = $request->request->all('variants');

            if (empty($errors)) {
                $product
                    ->setName($name)
                    ->setDescription($description ?: null)
                    ->setCategory($category ?: null)
                    ->setMaterial($material ?: null)
                    ->setBasePriceCents((int) round(((float) str_replace(',', '.', $priceEuros)) * 100));

                if ($isNew) {
                    $product->setSlug(strtolower((string) $slugger->slug($name)));
                    $em->persist($product);
                }

                $this->syncVariants($product, $variantsInput, $em);
                $this->syncImages($product, $request);

                $em->flush();
                $this->addFlash('success', $isNew ? 'Produit créé en brouillon.' : 'Produit mis à jour.');
                return $this->redirectToRoute('app_admin_product_detail', ['id' => $product->getId()]);
            }
        }

        return $this->render('admin/product/form.html.twig', [
            'product' => $product,
            'is_new' => $isNew,
            'errors' => $errors,
        ]);
    }

    /**
     * Handle image uploads + removals.
     * - `images[]` files are moved to upload dir, paths appended
     * - `remove_images[]` paths are filtered out + files deleted
     */
    private function syncImages(Product $product, Request $request): void
    {
        $current = $product->getImages();

        $removed = $request->request->all()['remove_images'] ?? [];
        if (!is_array($removed)) {
            $removed = [];
        }

        foreach ($removed as $path) {
            $path = (string) $path;
            $current = array_values(array_filter($current, fn($p) => $p !== $path));
            $absolute = $this->getParameter('kernel.project_dir') . '/public' . $path;
            if (is_file($absolute) && str_starts_with($path, self::UPLOAD_PUBLIC_PREFIX)) {
                @unlink($absolute);
            }
        }

        /** @var UploadedFile[] $uploaded */
        $uploaded = $request->files->all('images');
        foreach ($uploaded as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }
            $mime = $file->getMimeType();
            if (!$mime || !in_array($mime, self::ALLOWED_MIME, true)) {
                continue;
            }
            $filename = bin2hex(random_bytes(12)) . '.' . ($file->guessExtension() ?: 'jpg');
            try {
                $file->move($this->uploadDir, $filename);
                $current[] = self::UPLOAD_PUBLIC_PREFIX . $filename;
            } catch (FileException) {
                // skip
            }
        }

        $product->setImages(array_values($current));
    }

    /**
     * Sync variants from form data. Removes variants not kept; updates existing; creates new.
     * Expected data shape per variant: id (optional), size, color, color_hex, sku.
     */
    private function syncVariants(Product $product, array $variantsInput, EntityManagerInterface $em): void
    {
        $keepIds = [];
        foreach ($variantsInput as $row) {
            $size = trim((string) ($row['size'] ?? ''));
            $color = trim((string) ($row['color'] ?? ''));
            $hex = trim((string) ($row['color_hex'] ?? '')) ?: null;
            $sku = trim((string) ($row['sku'] ?? ''));

            if ($size === '' || $color === '' || $sku === '') {
                continue;
            }

            $id = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;
            if ($id) {
                $existing = $product->getVariants()->filter(fn($v) => $v->getId() === $id)->first();
                if ($existing) {
                    $existing->setSize($size)->setColor($color)->setColorHex($hex)->setSku($sku);
                    $keepIds[] = $id;
                    continue;
                }
            }
            $new = (new ProductVariant())
                ->setSize($size)->setColor($color)->setColorHex($hex)->setSku($sku);
            $product->addVariant($new);
        }

        // Remove variants not kept
        foreach ($product->getVariants() as $v) {
            if ($v->getId() !== null && !in_array($v->getId(), $keepIds, true)) {
                // Only remove if it wasn't just created (id null means just added this request)
                $found = false;
                foreach ($variantsInput as $row) {
                    if (isset($row['id']) && (int) $row['id'] === $v->getId()) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $em->remove($v);
                }
            }
        }
    }
}
