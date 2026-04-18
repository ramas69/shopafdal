<?php

namespace App\Entity;

use App\Repository\InvitationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvitationRepository::class)]
#[ORM\Table(name: 'invitations')]
#[ORM\UniqueConstraint(name: 'uniq_invitation_token', columns: ['token'])]
class Invitation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Company $company;

    #[ORM\Column(length: 64)]
    private string $token;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $this->createdAt->modify('+7 days');
        $this->token = bin2hex(random_bytes(24));
    }

    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $v): self { $this->email = strtolower($v); return $this; }
    public function getCompany(): Company { return $this->company; }
    public function setCompany(Company $v): self { $this->company = $v; return $this; }
    public function getToken(): string { return $this->token; }
    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function setExpiresAt(\DateTimeImmutable $v): self { $this->expiresAt = $v; return $this; }
    public function getAcceptedAt(): ?\DateTimeImmutable { return $this->acceptedAt; }
    public function setAcceptedAt(?\DateTimeImmutable $v): self { $this->acceptedAt = $v; return $this; }
    public function getRevokedAt(): ?\DateTimeImmutable { return $this->revokedAt; }
    public function setRevokedAt(?\DateTimeImmutable $v): self { $this->revokedAt = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isPending(): bool
    {
        return $this->acceptedAt === null
            && $this->revokedAt === null
            && $this->expiresAt > new \DateTimeImmutable();
    }
}
