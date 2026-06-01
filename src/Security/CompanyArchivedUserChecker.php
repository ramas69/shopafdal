<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Bloque la connexion des utilisateurs rattachés à une entreprise archivée.
 */
final class CompanyArchivedUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && $user->getCompany()?->isArchived()) {
            throw new CustomUserMessageAccountStatusException('Votre entreprise est archivée. Contactez Afdal pour réactiver votre accès.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
