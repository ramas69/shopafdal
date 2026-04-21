<?php

namespace App\DataFixtures;

use App\Entity\Antenna;
use App\Entity\Company;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Enum\ProductStatus;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = (new User())
            ->setEmail('admin@afdal.fr')
            ->setFullName('Admin Afdal')
            ->setRole(UserRole::ADMIN);
        $admin->setPassword($this->hasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        $alpha = (new Company())->setName('Groupe Alpha')->setSlug('groupe-alpha')->setSiret('12345678900010');
        $manager->persist($alpha);

        $alphaParis = (new Antenna())
            ->setName('Siège Paris')
            ->setAddressLine('12 rue de Rivoli')
            ->setPostalCode('75001')->setCity('Paris')->setPhone('0142000001');
        $alpha->addAntenna($alphaParis);

        $alphaLyon = (new Antenna())
            ->setName('Bureau Lyon')
            ->setAddressLine('45 rue de la République')
            ->setPostalCode('69002')->setCity('Lyon')->setPhone('0472000002');
        $alpha->addAntenna($alphaLyon);

        $alphaManager = (new User())
            ->setEmail('marie@groupe-alpha.fr')
            ->setFullName('Marie Dupont')
            ->setRole(UserRole::CLIENT_MANAGER)
            ->setCompany($alpha);
        $alphaManager->setPassword($this->hasher->hashPassword($alphaManager, 'client123'));
        $manager->persist($alphaManager);

        $beta = (new Company())->setName('Beta SAS')->setSlug('beta-sas')->setSiret('98765432100020');
        $manager->persist($beta);

        $betaMarseille = (new Antenna())
            ->setName('Dépôt Marseille')
            ->setAddressLine('200 avenue du Prado')
            ->setPostalCode('13008')->setCity('Marseille')->setPhone('0491000003');
        $beta->addAntenna($betaMarseille);

        $betaManager = (new User())
            ->setEmail('jean@beta-sas.fr')
            ->setFullName('Jean Martin')
            ->setRole(UserRole::CLIENT_MANAGER)
            ->setCompany($beta);
        $betaManager->setPassword($this->hasher->hashPassword($betaManager, 'client123'));
        $manager->persist($betaManager);

        $productsData = [
            ['T-shirt Premium', 't-shirt-premium', 'T-shirt', 'Coton bio 180g/m²', 'T-shirt classique coupe droite, idéal pour marquage textile.', 850],
            ['Polo Business', 'polo-business', 'Polo', 'Piqué coton 220g/m²', 'Polo manches courtes, col et bas de manches côtelés.', 1650],
            ['Sweat-shirt Crew', 'sweat-shirt-crew', 'Sweat', 'Molleton 280g/m²', 'Sweat col rond, intérieur gratté.', 2450],
            ['Casquette 6 panneaux', 'casquette-6-panneaux', 'Couvre-chef', 'Sergé coton', 'Casquette structurée, fermeture métal.', 950],
            ['Tote bag Canvas', 'tote-bag-canvas', 'Accessoire', 'Toile coton 280g/m²', 'Sac cabas renforcé, 2 anses longues.', 550],
        ];

        $variants = [];
        foreach ($productsData as [$name, $slug, $cat, $mat, $desc, $price]) {
            $product = (new Product())
                ->setName($name)->setSlug($slug)
                ->setCategory($cat)->setMaterial($mat)
                ->setDescription($desc)
                ->setBasePriceCents($price);
            $product->publish();
            // Fixtures : tous les produits sont accessibles aux 2 entreprises démo
            $product->addAllowedCompany($alpha);
            $product->addAllowedCompany($beta);

            $sizes = ($cat === 'Couvre-chef' || $cat === 'Accessoire') ? ['TU'] : ['S', 'M', 'L', 'XL', 'XXL'];
            $colors = [
                ['Blanc', '#FFFFFF'],
                ['Noir', '#0F172A'],
                ['Bleu marine', '#1E3A8A'],
            ];
            foreach ($sizes as $size) {
                foreach ($colors as [$color, $hex]) {
                    $v = (new ProductVariant())
                        ->setSize($size)
                        ->setColor($color)
                        ->setColorHex($hex)
                        ->setSku(sprintf('%s-%s-%s', strtoupper(substr($slug, 0, 6)), $size, strtoupper(substr($color, 0, 3))));
                    $product->addVariant($v);
                    $variants[] = $v;
                }
            }
            $manager->persist($product);
        }

        $manager->flush();

        $sampleOrders = [
            ['company' => $alpha, 'antenna' => $alphaParis, 'user' => $alphaManager, 'status' => OrderStatus::DELIVERED, 'ref' => 'CMD-2026-0001'],
            ['company' => $alpha, 'antenna' => $alphaLyon, 'user' => $alphaManager, 'status' => OrderStatus::IN_PRODUCTION, 'ref' => 'CMD-2026-0002'],
            ['company' => $alpha, 'antenna' => $alphaParis, 'user' => $alphaManager, 'status' => OrderStatus::PLACED, 'ref' => 'CMD-2026-0003'],
            ['company' => $beta, 'antenna' => $betaMarseille, 'user' => $betaManager, 'status' => OrderStatus::CONFIRMED, 'ref' => 'CMD-2026-0004'],
        ];

        foreach ($sampleOrders as $data) {
            $order = (new Order())
                ->setReference($data['ref'])
                ->setCompany($data['company'])
                ->setAntenna($data['antenna'])
                ->setCreatedBy($data['user'])
                ->setStatus($data['status']);

            if ($data['status'] !== OrderStatus::DRAFT) {
                $order->setPlacedAt(new \DateTimeImmutable('-' . random_int(1, 30) . ' days'));
            }

            $lineCount = random_int(2, 4);
            $pickedKeys = (array) array_rand($variants, $lineCount);
            foreach ($pickedKeys as $idx) {
                $variant = $variants[$idx];
                $qty = random_int(5, 50);
                $item = (new OrderItem())
                    ->setVariant($variant)
                    ->setQuantity($qty)
                    ->setUnitPriceCents($variant->getProduct()->getBasePriceCents())
                    ->setMarking(['zone' => 'poitrine', 'size' => 'A4']);
                $order->addItem($item);
            }

            $manager->persist($order);
        }

        $manager->flush();
    }
}
