<?php

namespace App\Entity;

use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'orders')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private string $reference;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    private Company $company;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Antenna $antenna;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $createdBy;

    #[ORM\Column(enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::DRAFT;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $adminNotes = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $placedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, OrderItem> */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getReference(): string { return $this->reference; }
    public function setReference(string $v): self { $this->reference = $v; return $this; }
    public function getCompany(): Company { return $this->company; }
    public function setCompany(Company $v): self { $this->company = $v; return $this; }
    public function getAntenna(): Antenna { return $this->antenna; }
    public function setAntenna(Antenna $v): self { $this->antenna = $v; return $this; }
    public function getCreatedBy(): User { return $this->createdBy; }
    public function setCreatedBy(User $v): self { $this->createdBy = $v; return $this; }
    public function getStatus(): OrderStatus { return $this->status; }
    public function setStatus(OrderStatus $v): self { $this->status = $v; $this->updatedAt = new \DateTimeImmutable(); return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): self { $this->notes = $v; return $this; }
    public function getAdminNotes(): ?string { return $this->adminNotes; }
    public function setAdminNotes(?string $v): self { $this->adminNotes = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getPlacedAt(): ?\DateTimeImmutable { return $this->placedAt; }
    public function setPlacedAt(?\DateTimeImmutable $v): self { $this->placedAt = $v; return $this; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function getItems(): Collection { return $this->items; }

    public function addItem(OrderItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOrder($this);
        }
        return $this;
    }

    public function getTotalCents(): int
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->getUnitPriceCents() * $item->getQuantity();
        }
        return $total;
    }

    public function getTotalQuantity(): int
    {
        $qty = 0;
        foreach ($this->items as $item) {
            $qty += $item->getQuantity();
        }
        return $qty;
    }
}
