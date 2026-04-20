<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\Order;
use App\Entity\OrderMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderMessage>
 */
class OrderMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderMessage::class);
    }

    /** @return OrderMessage[] */
    public function findForOrder(Order $order): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.order = :o')
            ->setParameter('o', $order)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countUnreadForClientInCompany(Company $company): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->innerJoin('m.order', 'o')
            ->andWhere('o.company = :c')
            ->andWhere('m.readByClientAt IS NULL')
            ->setParameter('c', $company)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countUnreadForAdmin(): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.readByAdminAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markAllReadForClient(Order $order): void
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.readByClientAt', ':now')
            ->andWhere('m.order = :o')
            ->andWhere('m.readByClientAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('o', $order)
            ->getQuery()
            ->execute();
    }

    public function markAllReadForAdmin(Order $order): void
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.readByAdminAt', ':now')
            ->andWhere('m.order = :o')
            ->andWhere('m.readByAdminAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('o', $order)
            ->getQuery()
            ->execute();
    }
}
