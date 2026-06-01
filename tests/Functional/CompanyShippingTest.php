<?php

namespace App\Tests\Functional;

use App\Entity\Company;
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
}
