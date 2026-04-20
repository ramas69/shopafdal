<?php

namespace App\Entity;

use App\Enum\CompanyRole;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 120)]
    private string $fullName;

    #[ORM\Column(enumType: UserRole::class)]
    private UserRole $role = UserRole::CLIENT_MANAGER;

    #[ORM\Column]
    private string $password;

    #[ORM\ManyToOne(inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Company $company = null;

    #[ORM\Column(length: 10, enumType: CompanyRole::class, nullable: true)]
    private ?CompanyRole $companyRole = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = strtolower($email); return $this; }
    public function getFullName(): string { return $this->fullName; }
    public function setFullName(string $v): self { $this->fullName = $v; return $this; }
    public function getRole(): UserRole { return $this->role; }
    public function setRole(UserRole $role): self { $this->role = $role; return $this; }
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): self { $this->password = $password; return $this; }
    public function getCompany(): ?Company { return $this->company; }
    public function setCompany(?Company $company): self { $this->company = $company; return $this; }
    public function getCompanyRole(): ?CompanyRole { return $this->companyRole; }
    public function setCompanyRole(?CompanyRole $r): self { $this->companyRole = $r; return $this; }
    public function isCompanyOwner(): bool { return $this->companyRole === CompanyRole::OWNER; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $v): self { $this->active = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getLastLoginAt(): ?\DateTimeImmutable { return $this->lastLoginAt; }
    public function setLastLoginAt(?\DateTimeImmutable $v): self { $this->lastLoginAt = $v; return $this; }

    public function getUserIdentifier(): string { return $this->email; }

    public function getRoles(): array
    {
        $roles = [$this->role->value];
        if ($this->companyRole === CompanyRole::OWNER) {
            $roles[] = 'ROLE_COMPANY_OWNER';
        }
        return array_unique($roles);
    }

    public function eraseCredentials(): void {}

    public function isAdmin(): bool { return $this->role === UserRole::ADMIN; }
}
