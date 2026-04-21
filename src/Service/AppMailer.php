<?php

namespace App\Service;

use App\Entity\MailSettings;
use App\Repository\MailSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\Component\Mime\Email;

/**
 * Envoi d'emails via la config SMTP stockée en DB (MailSettings).
 * Fallback sur le MailerInterface par défaut (MAILER_DSN env) si pas configuré.
 *
 * Cache le Mailer construit pour la durée de la requête.
 */
class AppMailer
{
    private ?MailerInterface $dynamicMailer = null;
    private ?string $cachedDsn = null;

    public function __construct(
        private readonly MailSettingsRepository $settingsRepo,
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $defaultMailer,
        private readonly LoggerInterface $logger,
        private readonly BodyRendererInterface $bodyRenderer,
    ) {}

    public function getSettings(): MailSettings
    {
        return $this->settingsRepo->getOrCreate($this->em);
    }

    /**
     * Envoie un email. Si le "from" n'est pas défini, applique celui de MailSettings.
     */
    public function send(Email $email): void
    {
        $settings = $this->getSettings();

        if ($email->getFrom() === [] && $settings->getFromEmail() !== null) {
            $email->from(new Address($settings->getFromEmail(), $settings->getFromName() ?: ''));
        }

        // Les TemplatedEmail doivent être rendus en HTML/texte avant envoi.
        // Quand on utilise le MailerInterface par défaut, un event listener s'en
        // charge automatiquement ; mais notre Mailer dynamique n'est pas relié
        // à l'event dispatcher Symfony, donc on le fait à la main ici.
        if ($email instanceof TemplatedEmail) {
            $this->bodyRenderer->render($email);
        }

        $this->resolveMailer($settings)->send($email);
    }

    /**
     * Envoie un email de test avec la config fournie (sans la persister).
     *
     * Effectue d'abord un preflight TCP pour isoler les erreurs réseau
     * des erreurs SMTP/auth, puis tente l'envoi via Symfony Mailer.
     * Lance une exception avec un message explicite si l'envoi échoue.
     */
    public function sendTest(MailSettings $settings, string $to): void
    {
        $dsn = $settings->toDsn();
        if ($dsn === null) {
            throw new \RuntimeException('Configuration SMTP incomplète (host, port et email expéditeur requis).');
        }

        $this->preflightTcp($settings->getHost(), $settings->getPort());

        $transport = Transport::fromDsn($dsn);
        $mailer = new Mailer($transport);

        $from = $settings->getFromEmail() ?? 'noreply@afdal.local';
        $fromName = $settings->getFromName() ?? 'Afdal';

        $email = (new Email())
            ->from(new Address($from, $fromName))
            ->to($to)
            ->subject('Test de configuration SMTP — Afdal')
            ->text("Ce message confirme que ta configuration SMTP fonctionne.\n\nHôte : {$settings->getHost()}\nPort : {$settings->getPort()}\nChiffrement : {$settings->getEncryption()}");

        $mailer->send($email);
    }

    /**
     * Vérifie la joignabilité TCP de host:port avant de tenter le handshake SMTP.
     * Donne une erreur claire si l'hôte n'est pas résolvable ou si le port est bloqué
     * (firewall ISP, hôte invalide, etc.), plutôt qu'un message Symfony cryptique.
     */
    private function preflightTcp(string $host, int $port, int $timeoutSec = 5): void
    {
        // Résolution DNS
        $ip = @gethostbyname($host);
        if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            throw new \RuntimeException(sprintf('Impossible de résoudre l\'hôte "%s". Vérifie l\'orthographe.', $host));
        }

        // Ouverture socket TCP
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeoutSec);
        if ($socket === false) {
            throw new \RuntimeException(sprintf(
                'Impossible de joindre %s:%d (TCP). %s. Ton FAI bloque peut-être ce port, ou l\'hôte est incorrect.',
                $host,
                $port,
                $errstr ?: 'Connexion refusée',
            ));
        }
        fclose($socket);
    }

    public function notificationRecipientAdmin(): ?Address
    {
        $email = $this->getSettings()->getEffectiveAdminNotificationEmail();
        return $email ? new Address($email) : null;
    }

    /**
     * Envoi best-effort : log et swallow les erreurs pour ne pas faire planter
     * le checkout / la transition si le SMTP est HS ou non configuré.
     */
    public function sendSilently(Email $email, string $context = ''): bool
    {
        try {
            $this->send($email);
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('AppMailer: envoi échoué' . ($context ? ' [' . $context . ']' : ''), ['exception' => $e]);
            return false;
        }
    }

    private function resolveMailer(MailSettings $settings): MailerInterface
    {
        $dsn = $settings->toDsn();
        if ($dsn === null) {
            return $this->defaultMailer;
        }
        if ($this->dynamicMailer !== null && $this->cachedDsn === $dsn) {
            return $this->dynamicMailer;
        }
        try {
            $transport = Transport::fromDsn($dsn);
            $this->dynamicMailer = new Mailer($transport);
            $this->cachedDsn = $dsn;
            return $this->dynamicMailer;
        } catch (\Throwable $e) {
            $this->logger->error('AppMailer: DSN SMTP invalide, fallback sur MAILER_DSN env.', ['exception' => $e]);
            return $this->defaultMailer;
        }
    }
}
