<?php

namespace App\Command;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-admin', description: 'Crée un utilisateur admin Afdal')]
final class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $users,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de l\'admin')
            ->addArgument('fullName', InputArgument::REQUIRED, 'Nom complet')
            ->addArgument('password', InputArgument::REQUIRED, 'Mot de passe');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = strtolower(trim((string) $input->getArgument('email')));
        $fullName = trim((string) $input->getArgument('fullName'));
        $password = (string) $input->getArgument('password');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Email invalide.');
            return Command::FAILURE;
        }
        if (strlen($password) < 8) {
            $io->error('Le mot de passe doit contenir au moins 8 caractères.');
            return Command::FAILURE;
        }
        if ($this->users->findOneBy(['email' => $email])) {
            $io->error(sprintf('Un utilisateur avec l\'email "%s" existe déjà.', $email));
            return Command::FAILURE;
        }

        $user = (new User())
            ->setEmail($email)
            ->setFullName($fullName)
            ->setRole(UserRole::ADMIN);
        $user->setPassword($this->hasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Admin "%s" créé avec succès (id=%d).', $email, $user->getId()));
        return Command::SUCCESS;
    }
}
