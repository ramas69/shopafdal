<?php

namespace App\Entity;

use App\Repository\OrderEventRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderEventRepository::class)]
#[ORM\Table(name: 'order_events')]
#[ORM\Index(name: 'idx_oe_order_created', columns: ['order_id', 'created_at'])]
class OrderEvent
{
    public const TYPE_CREATED = 'created';
    public const TYPE_STATUS_CHANGED = 'status_changed';
    public const TYPE_ITEMS_EDITED = 'items_edited';
    public const TYPE_ANTENNA_CHANGED = 'antenna_changed';
    public const TYPE_NOTES_UPDATED = 'notes_updated';
    public const TYPE_CANCELLED = 'cancelled';
    public const TYPE_ADMIN_NOTE = 'admin_note';
    public const TYPE_BAT_UPLOADED = 'bat_uploaded';
    public const TYPE_BAT_APPROVED = 'bat_approved';
    public const TYPE_BAT_REJECTED = 'bat_rejected';
    public const TYPE_SHIPPING_UPDATED = 'shipping_updated';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $actor = null;

    #[ORM\Column(length: 30)]
    private string $type;

    #[ORM\Column(length: 255)]
    private string $summary;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $data = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getOrder(): Order { return $this->order; }
    public function setOrder(Order $v): self { $this->order = $v; return $this; }
    public function getActor(): ?User { return $this->actor; }
    public function setActor(?User $v): self { $this->actor = $v; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $v): self { $this->type = $v; return $this; }
    public function getSummary(): string { return $this->summary; }
    public function setSummary(string $v): self { $this->summary = $v; return $this; }
    public function getData(): ?array { return $this->data; }
    public function setData(?array $v): self { $this->data = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isAdminEvent(): bool
    {
        return $this->actor?->isAdmin() ?? false;
    }
}
