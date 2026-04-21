<?php

namespace App\Entity;

use App\Repository\MailSettingsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MailSettingsRepository::class)]
#[ORM\Table(name: 'mail_settings')]
class MailSettings
{
    public const ENCRYPTION_NONE = 'none';
    public const ENCRYPTION_SSL = 'ssl';
    public const ENCRYPTION_TLS = 'tls';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $host = null;

    #[ORM\Column(nullable: true)]
    private ?int $port = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(length: 10, options: ['default' => self::ENCRYPTION_TLS])]
    private string $encryption = self::ENCRYPTION_TLS;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $fromEmail = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $fromName = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $adminNotificationEmail = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastTestedAt = null;

    #[ORM\Column(nullable: true)]
    private ?bool $lastTestOk = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $lastTestError = null;

    public function getId(): ?int { return $this->id; }
    public function getHost(): ?string { return $this->host; }
    public function setHost(?string $v): self { $this->host = $v ?: null; return $this; }
    public function getPort(): ?int { return $this->port; }
    public function setPort(?int $v): self { $this->port = $v; return $this; }
    public function getUsername(): ?string { return $this->username; }
    public function setUsername(?string $v): self { $this->username = $v ?: null; return $this; }
    public function getPassword(): ?string { return $this->password; }
    public function setPassword(?string $v): self { $this->password = $v ?: null; return $this; }
    public function getEncryption(): string { return $this->encryption; }
    public function setEncryption(string $v): self { $this->encryption = $v; return $this; }
    public function getFromEmail(): ?string { return $this->fromEmail; }
    public function setFromEmail(?string $v): self { $this->fromEmail = $v ?: null; return $this; }
    public function getFromName(): ?string { return $this->fromName; }
    public function setFromName(?string $v): self { $this->fromName = $v ?: null; return $this; }
    public function getAdminNotificationEmail(): ?string { return $this->adminNotificationEmail; }
    public function setAdminNotificationEmail(?string $v): self { $this->adminNotificationEmail = $v ?: null; return $this; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function touch(): self { $this->updatedAt = new \DateTimeImmutable(); return $this; }

    public function getLastTestedAt(): ?\DateTimeImmutable { return $this->lastTestedAt; }
    public function getLastTestOk(): ?bool { return $this->lastTestOk; }
    public function getLastTestError(): ?string { return $this->lastTestError; }

    public function recordTest(bool $ok, ?string $error = null): self
    {
        $this->lastTestedAt = new \DateTimeImmutable();
        $this->lastTestOk = $ok;
        if ($ok || $error === null) {
            $this->lastTestError = null;
        } else {
            $this->lastTestError = mb_substr($error, 0, 500);
        }
        return $this;
    }

    public function resetTestStatus(): self
    {
        $this->lastTestedAt = null;
        $this->lastTestOk = null;
        $this->lastTestError = null;
        return $this;
    }

    public function isConfigured(): bool
    {
        return $this->host !== null && $this->port !== null && $this->fromEmail !== null;
    }

    /**
     * User utilisé pour l'authentification SMTP : username explicite sinon fromEmail.
     * La plupart des providers utilisent l'email lui-même comme login.
     */
    public function getAuthUser(): ?string
    {
        return $this->username ?? $this->fromEmail;
    }

    /**
     * Destinataire final des emails "nouvelle commande" : champ dédié sinon fromEmail.
     */
    public function getEffectiveAdminNotificationEmail(): ?string
    {
        return $this->adminNotificationEmail ?? $this->fromEmail;
    }

    /**
     * Construit un DSN Symfony Mailer depuis la config.
     * Format : smtp[s]://user:pass@host:port?verify_peer=1
     */
    public function toDsn(): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }
        $scheme = $this->encryption === self::ENCRYPTION_SSL ? 'smtps' : 'smtp';
        $auth = '';
        $user = $this->getAuthUser();
        if ($user !== null) {
            $auth = rawurlencode($user);
            if ($this->password !== null) {
                $auth .= ':' . rawurlencode($this->password);
            }
            $auth .= '@';
        }
        return sprintf('%s://%s%s:%d', $scheme, $auth, $this->host, $this->port);
    }
}
