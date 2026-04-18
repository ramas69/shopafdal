<?php

namespace App\Entity;

use App\Repository\ProductVariantRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductVariantRepository::class)]
#[ORM\Table(name: 'product_variants')]
#[ORM\UniqueConstraint(name: 'uniq_variant_sku', columns: ['sku'])]
class ProductVariant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'variants')]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\Column(length: 20)]
    private string $size;

    #[ORM\Column(length: 40)]
    private string $color;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $colorHex = null;

    #[ORM\Column(length: 80)]
    private string $sku;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $stock = null;

    public function getId(): ?int { return $this->id; }
    public function getProduct(): Product { return $this->product; }
    public function setProduct(Product $product): self { $this->product = $product; return $this; }
    public function getSize(): string { return $this->size; }
    public function setSize(string $v): self { $this->size = $v; return $this; }
    public function getColor(): string { return $this->color; }
    public function setColor(string $v): self { $this->color = $v; return $this; }
    public function getColorHex(): ?string { return $this->colorHex; }
    public function setColorHex(?string $v): self { $this->colorHex = $v; return $this; }
    public function getSku(): string { return $this->sku; }
    public function setSku(string $v): self { $this->sku = $v; return $this; }
    public function getStock(): ?int { return $this->stock; }
    public function setStock(?int $v): self { $this->stock = $v; return $this; }
}
