<?php

namespace App\Repository;

use App\Entity\Invitation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invitation>
 */
class InvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invitation::class);
    }

    public function findValidByToken(string $token): ?Invitation
    {
        $invitation = $this->findOneBy(['token' => $token]);
        if (!$invitation || !$invitation->isPending()) {
            return null;
        }
        return $invitation;
    }
}
