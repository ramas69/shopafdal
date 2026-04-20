<?php

namespace App\Entity;

use App\Enum\MarkingStatus;
use App\Repository\MarkingAssetRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MarkingAssetRepository::class)]
#[ORM\Table(name: 'marking_assets')]
#[ORM\Index(name: 'idx_marking_item_version', columns: ['order_item_id', 'version'])]
class MarkingAsset
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private OrderItem $orderItem;

    #[ORM\Column(length: 255)]
    private string $logoPath;

    #[ORM\Column(length: 20, enumType: MarkingStatus::class, options: ['default' => 'pending'])]
    private MarkingStatus $status = MarkingStatus::PENDING;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $feedback = null;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $version = 1;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $uploadedBy;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $reviewedBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    public function __construct(OrderItem $item, User $uploader, string $logoPath, int $version = 1)
    {
        $this->orderItem = $item;
        $this->uploadedBy = $uploader;
        $this->logoPath = $logoPath;
        $this->version = $version;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getOrderItem(): OrderItem { return $this->orderItem; }
    public function getLogoPath(): string { return $this->logoPath; }
    public function getStatus(): MarkingStatus { return $this->status; }
    public function getFeedback(): ?string { return $this->feedback; }
    public function getVersion(): int { return $this->version; }
    public function getUploadedBy(): User { return $this->uploadedBy; }
    public function getReviewedBy(): ?User { return $this->reviewedBy; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getReviewedAt(): ?\DateTimeImmutable { return $this->reviewedAt; }

    public function approve(User $admin): self
    {
        $this->status = MarkingStatus::APPROVED;
        $this->reviewedBy = $admin;
        $this->reviewedAt = new \DateTimeImmutable();
        $this->feedback = null;
        return $this;
    }

    public function reject(User $admin, ?string $feedback): self
    {
        $this->status = MarkingStatus::REJECTED;
        $this->reviewedBy = $admin;
        $this->reviewedAt = new \DateTimeImmutable();
        $this->feedback = $feedback;
        return $this;
    }

    public function isPending(): bool { return $this->status === MarkingStatus::PENDING; }
    public function isApproved(): bool { return $this->status === MarkingStatus::APPROVED; }
    public function isRejected(): bool { return $this->status === MarkingStatus::REJECTED; }
}
