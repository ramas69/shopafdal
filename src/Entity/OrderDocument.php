<?php

namespace App\Entity;

use App\Repository\OrderDocumentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderDocumentRepository::class)]
#[ORM\Table(name: 'order_documents')]
#[ORM\Index(name: 'idx_orderdoc_order', columns: ['order_id'])]
class OrderDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\Column(length: 255)]
    private string $path;

    #[ORM\Column(length: 255)]
    private string $originalName;

    #[ORM\Column(length: 100)]
    private string $mimeType;

    #[ORM\Column]
    private int $sizeBytes;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $uploadedBy;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Order $order, User $uploader, string $path, string $originalName, string $mimeType, int $sizeBytes)
    {
        $this->order = $order;
        $this->uploadedBy = $uploader;
        $this->path = $path;
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
        $this->sizeBytes = $sizeBytes;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getOrder(): Order { return $this->order; }
    public function getPath(): string { return $this->path; }
    public function getOriginalName(): string { return $this->originalName; }
    public function getMimeType(): string { return $this->mimeType; }
    public function getSizeBytes(): int { return $this->sizeBytes; }
    public function getUploadedBy(): User { return $this->uploadedBy; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isPdf(): bool { return $this->mimeType === 'application/pdf'; }
    public function isImage(): bool { return str_starts_with($this->mimeType, 'image/'); }

    public function isFromAdmin(): bool
    {
        return $this->uploadedBy->isAdmin();
    }
}
