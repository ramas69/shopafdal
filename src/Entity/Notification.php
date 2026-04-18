<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notifications')]
#[ORM\Index(name: 'idx_notif_recipient_read', columns: ['recipient_id', 'read_at'])]
class Notification
{
    public const TYPE_INFO = 'info';
    public const TYPE_SUCCESS = 'success';
    public const TYPE_WARNING = 'warning';
    public const TYPE_DESTRUCTIVE = 'destructive';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $recipient;

    #[ORM\Column(length: 20, options: ['default' => 'info'])]
    private string $type = self::TYPE_INFO;

    #[ORM\Column(length: 180)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $linkUrl = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getRecipient(): User { return $this->recipient; }
    public function setRecipient(User $v): self { $this->recipient = $v; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $v): self { $this->type = $v; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $v): self { $this->title = $v; return $this; }
    public function getMessage(): ?string { return $this->message; }
    public function setMessage(?string $v): self { $this->message = $v; return $this; }
    public function getLinkUrl(): ?string { return $this->linkUrl; }
    public function setLinkUrl(?string $v): self { $this->linkUrl = $v; return $this; }
    public function getReadAt(): ?\DateTimeImmutable { return $this->readAt; }
    public function setReadAt(?\DateTimeImmutable $v): self { $this->readAt = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isRead(): bool { return $this->readAt !== null; }
}
