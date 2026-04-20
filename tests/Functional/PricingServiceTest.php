<?php

namespace App\Tests\Functional;

use App\Entity\CompanyPrice;
use App\Entity\PriceTier;
use App\Service\PricingService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PricingServiceTest extends KernelTestCase
{
    use TestDataTrait;

    public function testBasePriceWhenNoTierAndNoCompanyPrice(): void
    {
        self::bootKernel();
        $pricing = self::getContainer()->get(PricingService::class);

        $product = $this->createProduct('Base', 2000);

        self::assertSame(2000, $pricing->resolveUnitPrice($product, null, 1));
        self::assertSame(2000, $pricing->resolveUnitPrice($product, null, 100));
    }

    public function testVolumeTierKicksIn(): void
    {
        self::bootKernel();
        $pricing = self::getContainer()->get(PricingService::class);

        $product = $this->createProduct('Volume', 2000);
        $tier = (new PriceTier())->setProduct($product)->setMinQty(20)->setUnitPriceCents(1600);
        $this->em()->persist($tier);
        $this->em()->flush();

        self::assertSame(2000, $pricing->resolveUnitPrice($product, null, 10));
        self::assertSame(1600, $pricing->resolveUnitPrice($product, null, 20));
        self::assertSame(1600, $pricing->resolveUnitPrice($product, null, 50));
    }

    public function testCompanyPriceOverridesEverything(): void
    {
        self::bootKernel();
        $pricing = self::getContainer()->get(PricingService::class);

        [$company] = $this->createCompanyWithAntenna();
        $product = $this->createProduct('Negotiated', 2000);
        $tier = (new PriceTier())->setProduct($product)->setMinQty(20)->setUnitPriceCents(1600);
        $cp = (new CompanyPrice())->setCompany($company)->setProduct($product)->setUnitPriceCents(1000);
        $this->em()->persist($tier);
        $this->em()->persist($cp);
        $this->em()->flush();

        // Même qty élevée, le tarif négocié écrase le palier
        self::assertSame(1000, $pricing->resolveUnitPrice($product, $company, 1));
        self::assertSame(1000, $pricing->resolveUnitPrice($product, $company, 100));
    }
}
