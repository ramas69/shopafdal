<?php

namespace App\Entity;

use App\Repository\CompanyPriceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompanyPriceRepository::class)]
#[ORM\Table(name: 'company_prices')]
#[ORM\UniqueConstraint(name: 'uniq_company_product', columns: ['company_id', 'product_id'])]
class CompanyPrice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Company $company;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(type: 'integer')]
    private int $unitPriceCents = 0;

    public function getId(): ?int { return $this->id; }
    public function getCompany(): Company { return $this->company; }
    public function setCompany(Company $v): self { $this->company = $v; return $this; }
    public function getProduct(): Product { return $this->product; }
    public function setProduct(Product $v): self { $this->product = $v; return $this; }
    public function getUnitPriceCents(): int { return $this->unitPriceCents; }
    public function setUnitPriceCents(int $v): self { $this->unitPriceCents = max(0, $v); return $this; }
}
