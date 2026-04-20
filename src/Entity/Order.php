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
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $inProductionAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $shippedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $carrier = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $trackingNumber = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $estimatedDeliveryAt = null;

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
    public function setStatus(OrderStatus $v): self
    {
        $this->status = $v;
        $now = new \DateTimeImmutable();
        $this->updatedAt = $now;
        match ($v) {
            OrderStatus::PLACED => $this->placedAt ??= $now,
            OrderStatus::CONFIRMED => $this->confirmedAt ??= $now,
            OrderStatus::IN_PRODUCTION => $this->inProductionAt ??= $now,
            OrderStatus::SHIPPED => $this->shippedAt ??= $now,
            OrderStatus::DELIVERED => $this->deliveredAt ??= $now,
            OrderStatus::CANCELLED => $this->cancelledAt ??= $now,
            default => null,
        };
        return $this;
    }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): self { $this->notes = $v; return $this; }
    public function getAdminNotes(): ?string { return $this->adminNotes; }
    public function setAdminNotes(?string $v): self { $this->adminNotes = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getPlacedAt(): ?\DateTimeImmutable { return $this->placedAt; }
    public function setPlacedAt(?\DateTimeImmutable $v): self { $this->placedAt = $v; return $this; }
    public function getConfirmedAt(): ?\DateTimeImmutable { return $this->confirmedAt; }
    public function getInProductionAt(): ?\DateTimeImmutable { return $this->inProductionAt; }
    public function getShippedAt(): ?\DateTimeImmutable { return $this->shippedAt; }
    public function getDeliveredAt(): ?\DateTimeImmutable { return $this->deliveredAt; }
    public function getCancelledAt(): ?\DateTimeImmutable { return $this->cancelledAt; }

    public function getStatusTimestamp(OrderStatus $status): ?\DateTimeImmutable
    {
        return match ($status) {
            OrderStatus::PLACED => $this->placedAt,
            OrderStatus::CONFIRMED => $this->confirmedAt,
            OrderStatus::IN_PRODUCTION => $this->inProductionAt,
            OrderStatus::SHIPPED => $this->shippedAt,
            OrderStatus::DELIVERED => $this->deliveredAt,
            OrderStatus::CANCELLED => $this->cancelledAt,
            default => null,
        };
    }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    public function getCarrier(): ?string { return $this->carrier; }
    public function setCarrier(?string $v): self { $this->carrier = $v ?: null; return $this; }
    public function getTrackingNumber(): ?string { return $this->trackingNumber; }
    public function setTrackingNumber(?string $v): self { $this->trackingNumber = $v ?: null; return $this; }
    public function getEstimatedDeliveryAt(): ?\DateTimeImmutable { return $this->estimatedDeliveryAt; }
    public function setEstimatedDeliveryAt(?\DateTimeImmutable $v): self { $this->estimatedDeliveryAt = $v; return $this; }
    public function setShippedAt(?\DateTimeImmutable $v): self { $this->shippedAt = $v; return $this; }

    public function getTrackingUrl(): ?string
    {
        if (!$this->carrier || !$this->trackingNumber) {
            return null;
        }
        $t = rawurlencode($this->trackingNumber);
        return match (strtolower($this->carrier)) {
            'chronopost' => 'https://www.chronopost.fr/tracking-no-cms/suivi-page?listeNumerosLT=' . $t,
            'colissimo' => 'https://www.laposte.fr/outils/suivre-vos-envois?code=' . $t,
            'dpd' => 'https://www.dpd.fr/trace/' . $t,
            'ups' => 'https://www.ups.com/track?tracknum=' . $t,
            default => null,
        };
    }

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
