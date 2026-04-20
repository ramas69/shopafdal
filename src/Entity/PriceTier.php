<?php

namespace App\Entity;

use App\Repository\PriceTierRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PriceTierRepository::class)]
#[ORM\Table(name: 'price_tiers')]
#[ORM\UniqueConstraint(name: 'uniq_tier_product_minqty', columns: ['product_id', 'min_qty'])]
class PriceTier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(type: 'integer')]
    private int $minQty = 1;

    #[ORM\Column(type: 'integer')]
    private int $unitPriceCents = 0;

    public function getId(): ?int { return $this->id; }
    public function getProduct(): Product { return $this->product; }
    public function setProduct(Product $v): self { $this->product = $v; return $this; }
    public function getMinQty(): int { return $this->minQty; }
    public function setMinQty(int $v): self { $this->minQty = max(1, $v); return $this; }
    public function getUnitPriceCents(): int { return $this->unitPriceCents; }
    public function setUnitPriceCents(int $v): self { $this->unitPriceCents = max(0, $v); return $this; }
}
