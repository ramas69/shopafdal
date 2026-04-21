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

    /**
     * IDs des entreprises ayant au moins une invitation encore en cours (non acceptée, non révoquée, non expirée).
     *
     * @return array<int, true> map companyId => true, facile à tester avec isset()
     */
    public function pendingCompanyIdMap(): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('DISTINCT IDENTITY(i.company) AS company_id')
            ->where('i.acceptedAt IS NULL')
            ->andWhere('i.revokedAt IS NULL')
            ->andWhere('i.expiresAt > :now')
            ->andWhere('i.company IS NOT NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['company_id']] = true;
        }
        return $map;
    }
}
