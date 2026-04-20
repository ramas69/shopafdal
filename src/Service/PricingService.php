<?php

namespace App\Service;

use App\Entity\Company;
use App\Entity\Product;
use App\Repository\CompanyPriceRepository;
use App\Repository\PriceTierRepository;

final class PricingService
{
    public function __construct(
        private PriceTierRepository $tiers,
        private CompanyPriceRepository $companyPrices,
    ) {}

    /**
     * Retourne le prix unitaire (cents) pour (product, company, qty).
     * Ordre de résolution :
     * 1. CompanyPrice négocié pour cette entreprise (override tout)
     * 2. Palier volume dont minQty ≤ qty (le plus proche)
     * 3. product.basePriceCents
     */
    public function resolveUnitPrice(Product $product, ?Company $company, int $qty): int
    {
        if ($company) {
            $negotiated = $this->companyPrices->findForCompanyAndProduct($company, $product);
            if ($negotiated) {
                return $negotiated->getUnitPriceCents();
            }
        }

        $tiers = $this->tiers->findForProduct($product);
        $best = $product->getBasePriceCents();
        foreach ($tiers as $t) {
            if ($qty >= $t->getMinQty()) {
                $best = $t->getUnitPriceCents();
            }
        }
        return $best;
    }

    /**
     * Renvoie les paliers public pour affichage catalogue (sans override entreprise).
     * @return array<int, array{min_qty:int, unit_cents:int}>
     */
    public function publicTiers(Product $product): array
    {
        $rows = [];
        foreach ($this->tiers->findForProduct($product) as $t) {
            $rows[] = ['min_qty' => $t->getMinQty(), 'unit_cents' => $t->getUnitPriceCents()];
        }
        return $rows;
    }
}
