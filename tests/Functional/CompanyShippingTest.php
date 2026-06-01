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
}
