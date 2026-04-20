<?php

namespace App\Entity;

use App\Repository\OrderMessageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderMessageRepository::class)]
#[ORM\Table(name: 'order_messages')]
#[ORM\Index(name: 'idx_om_order_created', columns: ['order_id', 'created_at'])]
class OrderMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $author;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readByClientAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readByAdminAt = null;

    public function __construct(Order $order, User $author, string $body)
    {
        $this->order = $order;
        $this->author = $author;
        $this->body = $body;
        $this->createdAt = new \DateTimeImmutable();
        // Auto-mark read for author's side
        if ($author->isAdmin()) {
            $this->readByAdminAt = $this->createdAt;
        } else {
            $this->readByClientAt = $this->createdAt;
        }
    }

    public function getId(): ?int { return $this->id; }
    public function getOrder(): Order { return $this->order; }
    public function getAuthor(): User { return $this->author; }
    public function getBody(): string { return $this->body; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getReadByClientAt(): ?\DateTimeImmutable { return $this->readByClientAt; }
    public function getReadByAdminAt(): ?\DateTimeImmutable { return $this->readByAdminAt; }

    public function markReadByClient(): self
    {
        $this->readByClientAt ??= new \DateTimeImmutable();
        return $this;
    }
    public function markReadByAdmin(): self
    {
        $this->readByAdminAt ??= new \DateTimeImmutable();
        return $this;
    }

    public function isFromAdmin(): bool
    {
        return $this->author->isAdmin();
    }
}
