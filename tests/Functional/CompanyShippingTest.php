<?php

namespace App\Tests\Functional;

use App\Entity\Company;
use App\Enum\CompanyRole;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CompanyShippingTest extends WebTestCase
{
    use TestDataTrait;

    public function testFreeShippingDefaultsToFalse(): void
    {
        self::bootKernel();
        $company = (new Company())->setName('Acme')->setSlug('acme-test');
        self::assertFalse($company->isFreeShipping());

        $company->setFreeShipping(true);
        self::assertTrue($company->isFreeShipping());
    }

    public function testAdminTogglesFreeShipping(): void
    {
        $client = static::createClient();
        [$company] = $this->createCompanyWithAntenna();
        $admin = $this->createUser('admin');

        $client->loginUser($admin);

        $client->request('POST', '/admin/entreprises/' . $company->getId() . '/frais-port');
        self::assertResponseRedirects();
        $this->em()->clear();
        self::assertTrue($this->em()->getRepository(Company::class)->find($company->getId())->isFreeShipping());

        $client->request('POST', '/admin/entreprises/' . $company->getId() . '/frais-port');
        $this->em()->clear();
        self::assertFalse($this->em()->getRepository(Company::class)->find($company->getId())->isFreeShipping());
    }

    public function testClientCannotToggleFreeShipping(): void
    {
        $client = static::createClient();
        [$company] = $this->createCompanyWithAntenna();
        $user = $this->createUser('client', $company, CompanyRole::OWNER);

        $client->loginUser($user);
        $client->request('POST', '/admin/entreprises/' . $company->getId() . '/frais-port');

        self::assertResponseStatusCodeSame(403);
    }

    private function createOrder(\App\Entity\Company $company, \App\Entity\Antenna $antenna, \App\Entity\User $createdBy): \App\Entity\Order
    {
        $order = (new \App\Entity\Order())
            ->setReference('CMD-2026-' . random_int(100000, 999999))
            ->setCompany($company)
            ->setAntenna($antenna)
            ->setCreatedBy($createdBy)
            ->setStatus(\App\Enum\OrderStatus::PLACED);
        $this->em()->persist($order);
        $this->em()->flush();
        return $order;
    }

    public function testOrderDetailShowsShippingNote(): void
    {
        $client = static::createClient();
        [$company, $antenna] = $this->createCompanyWithAntenna();
        $owner = $this->createUser('client', $company, CompanyRole::OWNER);
        $order = $this->createOrder($company, $antenna, $owner);

        $client->loginUser($owner);

        // Off → "non compris"
        $client->request('GET', '/commandes/' . $order->getReference());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Frais de port non compris', $client->getResponse()->getContent());

        // On → "gratuits"
        $company->setFreeShipping(true);
        $this->em()->flush();
        $client->request('GET', '/commandes/' . $order->getReference());
        self::assertStringContainsString('Frais de port gratuits', $client->getResponse()->getContent());
    }
}
