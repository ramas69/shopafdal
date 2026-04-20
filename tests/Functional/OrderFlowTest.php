<?php

namespace App\Tests\Functional;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Enum\CompanyRole;
use App\Enum\OrderStatus;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrderFlowTest extends WebTestCase
{
    use TestDataTrait;

    public function testClientPlacesOrderAndAdminConfirms(): void
    {
        $client = static::createClient();
        [$company, $antenna] = $this->createCompanyWithAntenna();
        $owner = $this->createUser('client', $company, CompanyRole::OWNER);
        $admin = $this->createUser('admin');
        $product = $this->createProduct();
        $variant = $product->getVariants()->first();

        // Étape 1 : owner ajoute au panier via le form catalogue
        $client->loginUser($owner);
        $crawler = $client->request('GET', '/catalogue/' . $product->getSlug());
        self::assertResponseIsSuccessful();

        $client->request('POST', '/catalogue/' . $product->getSlug() . '/add', [
            'quantities' => [$variant->getId() => 5],
        ]);
        self::assertResponseRedirects('/panier');
        $client->followRedirect();

        // Étape 2 : checkout avec CGV
        $client->request('POST', '/commander', [
            'antenna_id' => $antenna->getId(),
            'notes' => 'Test order',
            'cgv_accepted' => '1',
        ]);
        self::assertResponseRedirects();

        // Étape 3 : commande créée en status PLACED
        $em = $this->em();
        $em->clear();
        $order = $em->getRepository(Order::class)->findOneBy(['company' => $company], ['id' => 'DESC']);
        self::assertNotNull($order);
        self::assertSame(OrderStatus::PLACED, $order->getStatus());
        self::assertSame(5, $order->getTotalQuantity());

        // Étape 4 : admin confirme
        $client->loginUser($admin);
        $client->request('POST', '/admin/commandes/' . $order->getReference() . '/transition', [
            'status' => 'confirmed',
        ]);
        self::assertResponseRedirects();

        $em->clear();
        $order = $em->getRepository(Order::class)->find($order->getId());
        self::assertSame(OrderStatus::CONFIRMED, $order->getStatus());
    }

    public function testCheckoutFailsWithoutCgvAcceptance(): void
    {
        $client = static::createClient();
        [$company, $antenna] = $this->createCompanyWithAntenna();
        $owner = $this->createUser('client', $company, CompanyRole::OWNER);
        $product = $this->createProduct();
        $variant = $product->getVariants()->first();

        $client->loginUser($owner);
        $client->request('POST', '/catalogue/' . $product->getSlug() . '/add', [
            'quantities' => [$variant->getId() => 3],
        ]);

        $client->request('POST', '/commander', [
            'antenna_id' => $antenna->getId(),
            // cgv_accepted absent
        ]);

        // Doit rester sur le form (pas de redirect vers confirmation)
        self::assertResponseIsSuccessful();
        // Pas de commande créée
        $count = $this->em()->getRepository(Order::class)->count(['company' => $company]);
        self::assertSame(0, $count);
    }
}
