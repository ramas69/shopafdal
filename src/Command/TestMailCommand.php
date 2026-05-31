<?php

namespace App\Command;

use App\Service\AppMailer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:mail:test',
    description: 'Envoie un email de test via la config SMTP enregistrée (MailSettings).',
)]
final class TestMailCommand extends Command
{
    public function __construct(private readonly AppMailer $mailer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('to', InputArgument::REQUIRED, 'Adresse email destinataire du test');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $to = (string) $input->getArgument('to');

        $settings = $this->mailer->getSettings();
        $io->section('Configuration SMTP active');
        $io->listing([
            'Hôte : ' . ($settings->getHost() ?? '(non défini)'),
            'Port : ' . ($settings->getPort() ?? '(non défini)'),
            'Chiffrement : ' . $settings->getEncryption(),
            'Expéditeur : ' . ($settings->getFromEmail() ?? '(non défini)'),
            'Auth user : ' . ($settings->getAuthUser() ?? '(non défini)'),
        ]);

        if (!$settings->isConfigured()) {
            $io->error('Configuration SMTP incomplète (host, port et email expéditeur requis). Renseigne-la dans Paramètres → Email SMTP.');
            return Command::FAILURE;
        }

        $io->text(sprintf('Envoi du test vers <info>%s</info>…', $to));

        try {
            $this->mailer->sendTest($settings, $to);
            $io->success(sprintf('Email de test envoyé à %s. Vérifie la boîte de réception (et les spams).', $to));
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Échec de l\'envoi : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
