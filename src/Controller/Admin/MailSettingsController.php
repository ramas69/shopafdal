<?php

namespace App\Controller\Admin;

use App\Entity\MailSettings;
use App\Service\AppMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin/parametres/email')]
#[IsGranted('ROLE_ADMIN')]
final class MailSettingsController extends AbstractController
{
    private const ENCRYPTIONS = [
        MailSettings::ENCRYPTION_NONE,
        MailSettings::ENCRYPTION_SSL,
        MailSettings::ENCRYPTION_TLS,
    ];

    #[Route('', name: 'app_admin_mail_settings', methods: ['GET', 'POST'])]
    public function edit(Request $request, AppMailer $mailer, EntityManagerInterface $em, ValidatorInterface $validator): Response
    {
        $settings = $mailer->getSettings();     // entité managée (DB)
        $formSettings = clone $settings;        // copie non-managée pour le rendu/test
        $errors = [];
        $testResult = null;

        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('action', '');
            if ($action === 'disconnect') {
                $settings
                    ->setHost(null)->setPort(null)
                    ->setUsername(null)->setPassword(null)
                    ->setEncryption(MailSettings::ENCRYPTION_TLS)
                    ->setFromEmail(null)->setFromName(null)
                    ->setAdminNotificationEmail(null)
                    ->touch()->resetTestStatus();
                $em->flush();
                $this->addFlash('success', 'Configuration SMTP déconnectée. Renseigne de nouveaux identifiants si besoin.');
                return $this->redirectToRoute('app_admin_mail_settings');
            }
            if ($action !== 'test') {
                $this->addFlash('error', 'Action invalide.');
                return $this->redirectToRoute('app_admin_mail_settings');
            }
            $this->hydrateFromRequest($formSettings, $request);
            $errors = $this->validate($formSettings, $validator);

            $to = trim((string) $request->request->get('test_email', ''));
            if ($to === '') {
                $errors['test_email'] = 'Email de test requis.';
            } else {
                $toViolations = $validator->validate($to, [new Assert\Email(message: 'Email de test invalide.')]);
                foreach ($toViolations as $v) {
                    $errors['test_email'] = $v->getMessage();
                }
            }

            if (empty($errors)) {
                try {
                    $mailer->sendTest($formSettings, $to);
                    // Test OK → on applique les valeurs du form + on enregistre directement
                    $this->hydrateFromRequest($settings, $request);
                    $settings->touch()->recordTest(true);
                    $em->flush();
                    $this->addFlash('success', sprintf('Connexion établie et configuration enregistrée. Email de test envoyé à %s.', $to));
                    return $this->redirectToRoute('app_admin_mail_settings');
                } catch (\Throwable $e) {
                    // Échec → on persiste uniquement le statut du test (les champs du form restent en mémoire)
                    $settings->recordTest(false, $e->getMessage());
                    $em->flush();
                    $formSettings->recordTest(false, $e->getMessage());
                    $testResult = ['ok' => false, 'error' => $this->explainMailerError($e), 'to' => $to];
                }
            }
        }

        $status = $request->isMethod('POST') && (!empty($errors) || ($testResult && !$testResult['ok']))
            ? Response::HTTP_UNPROCESSABLE_ENTITY
            : Response::HTTP_OK;
        return $this->render('admin/settings/mail.html.twig', [
            'settings' => $formSettings,
            'errors' => $errors,
            'encryptions' => self::ENCRYPTIONS,
            'test_result' => $testResult,
            'test_email_value' => (string) $request->request->get('test_email', ''),
        ], new Response(null, $status));
    }

    /**
     * Traduit les exceptions Mailer / réseau en messages clairs pour l'utilisateur,
     * avec une suggestion d'action concrète. Retourne ['summary' => ..., 'hint' => ..., 'raw' => ...].
     *
     * @return array{summary: string, hint: ?string, raw: string}
     */
    private function explainMailerError(\Throwable $e): array
    {
        $msg = $e->getMessage();
        $raw = $msg;

        // Auth : code 535 "credentials invalid" (le plus fréquent)
        if (preg_match('/\b535\b|credentials invalid|Authentication credentials invalid|Authentication failed/i', $msg)) {
            return [
                'summary' => 'Identifiant ou mot de passe SMTP incorrect.',
                'hint' => 'Vérifie que l\'email et le mot de passe sont bons. Sur certains hébergeurs (IONOS, OVH), il faut un mot de passe d\'application spécifique au compte mail, différent du mdp du compte principal.',
                'raw' => $raw,
            ];
        }

        // Connexion impossible (TCP, DNS, firewall)
        if (stripos($msg, 'Could not connect') !== false
            || stripos($msg, 'Connection could not be established') !== false
            || stripos($msg, 'Impossible de joindre') !== false
            || stripos($msg, 'résoudre l\'hôte') !== false) {
            return [
                'summary' => 'Impossible de joindre le serveur SMTP.',
                'hint' => 'Vérifie l\'hôte et le port. Ton fournisseur d\'accès bloque peut-être le port utilisé — essaie 587 (TLS) ou 465 (SSL).',
                'raw' => $raw,
            ];
        }

        // STARTTLS refusé
        if (stripos($msg, 'STARTTLS') !== false) {
            return [
                'summary' => 'Le serveur refuse le chiffrement STARTTLS sur ce port.',
                'hint' => 'Passe sur SSL (port 465) dans les options avancées, ou demande à ton hébergeur le bon port à utiliser.',
                'raw' => $raw,
            ];
        }

        // SSL / cert
        if (stripos($msg, 'SSL') !== false || stripos($msg, 'certificate') !== false) {
            return [
                'summary' => 'Erreur de chiffrement SSL/TLS.',
                'hint' => 'Essaie un autre mode de chiffrement (TLS sur 587 ou SSL sur 465) dans les options avancées.',
                'raw' => $raw,
            ];
        }

        // Timeout
        if (stripos($msg, 'timeout') !== false || stripos($msg, 'timed out') !== false) {
            return [
                'summary' => 'Le serveur SMTP ne répond pas (délai dépassé).',
                'hint' => 'Vérifie l\'hôte et le port. Si ça marche ailleurs, c\'est peut-être ton réseau qui bloque.',
                'raw' => $raw,
            ];
        }

        // Lecture impossible (souvent mauvais chiffrement pour le port)
        if (stripos($msg, 'Unable to read') !== false) {
            return [
                'summary' => 'Le serveur a renvoyé une réponse illisible.',
                'hint' => 'Le chiffrement choisi ne correspond pas au port. Essaie TLS sur 587 ou SSL sur 465.',
                'raw' => $raw,
            ];
        }

        // Refus (550, 553...) sur l'expéditeur
        if (preg_match('/\b(550|553|554)\b/', $msg) || stripos($msg, 'not allowed') !== false) {
            return [
                'summary' => 'Le serveur refuse l\'email expéditeur.',
                'hint' => 'L\'email expéditeur doit correspondre au compte SMTP utilisé. Sur la plupart des hébergeurs, tu ne peux pas envoyer depuis une adresse différente de l\'identifiant.',
                'raw' => $raw,
            ];
        }

        // Fallback : message brut sans fioriture
        return [
            'summary' => 'Connexion SMTP échouée.',
            'hint' => null,
            'raw' => $raw,
        ];
    }

    private function hydrateFromRequest(MailSettings $settings, Request $request): void
    {
        $settings
            ->setHost(trim((string) $request->request->get('host', '')))
            ->setPort((int) $request->request->get('port', 0) ?: null)
            ->setUsername(trim((string) $request->request->get('username', '')))
            ->setEncryption(in_array($request->request->get('encryption'), self::ENCRYPTIONS, true)
                ? (string) $request->request->get('encryption')
                : MailSettings::ENCRYPTION_TLS)
            ->setFromEmail(trim((string) $request->request->get('from_email', '')))
            ->setFromName(trim((string) $request->request->get('from_name', '')))
            ->setAdminNotificationEmail(trim((string) $request->request->get('admin_notification_email', '')));

        // Le password n'est rééxposé que si l'admin a explicitement saisi une nouvelle valeur.
        // Nom du champ "smtp_password" (pas "password") pour éviter que les password
        // managers le détectent comme un champ login.
        $password = (string) $request->request->get('smtp_password', '');
        if ($password !== '') {
            $settings->setPassword($password);
        }
    }

    /** @return array<string, string> */
    private function validate(MailSettings $settings, ValidatorInterface $validator): array
    {
        $errors = [];

        if ($settings->getHost() === null) {
            $errors['host'] = 'Hôte SMTP requis.';
        }
        if ($settings->getPort() === null || $settings->getPort() < 1 || $settings->getPort() > 65535) {
            $errors['port'] = 'Port invalide (1–65535).';
        }
        if ($settings->getFromEmail() === null) {
            $errors['from_email'] = 'Email expéditeur requis.';
        } else {
            $v = $validator->validate($settings->getFromEmail(), new Assert\Email());
            foreach ($v as $violation) {
                $errors['from_email'] = $violation->getMessage();
            }
        }
        if ($settings->getAdminNotificationEmail() !== null) {
            $v = $validator->validate($settings->getAdminNotificationEmail(), new Assert\Email());
            foreach ($v as $violation) {
                $errors['admin_notification_email'] = $violation->getMessage();
            }
        }
        return $errors;
    }
}
