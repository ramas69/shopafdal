<?php

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
#[ORM\Table(name: 'order_items')]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private Order $order;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ProductVariant $variant;

    #[ORM\Column]
    private int $quantity = 1;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $unitPriceCents = 0;

    /** @var array<string, mixed>|null marking config (zone, file, size) */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $marking = null;

    public function getId(): ?int { return $this->id; }
    public function getOrder(): Order { return $this->order; }
    public function setOrder(Order $v): self { $this->order = $v; return $this; }
    public function getVariant(): ProductVariant { return $this->variant; }
    public function setVariant(ProductVariant $v): self { $this->variant = $v; return $this; }
    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $v): self { $this->quantity = $v; return $this; }
    public function getUnitPriceCents(): int { return $this->unitPriceCents; }
    public function setUnitPriceCents(int $v): self { $this->unitPriceCents = $v; return $this; }
    public function getMarking(): ?array { return $this->marking; }
    public function setMarking(?array $v): self { $this->marking = $v; return $this; }
}
