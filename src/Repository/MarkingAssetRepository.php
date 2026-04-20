<?php

namespace App\Repository;

use App\Entity\MarkingAsset;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Enum\MarkingStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MarkingAsset>
 */
class MarkingAssetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MarkingAsset::class);
    }

    public function findLatestForItem(OrderItem $item): ?MarkingAsset
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.orderItem = :it')
            ->setParameter('it', $item)
            ->orderBy('m.version', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return MarkingAsset[] */
    public function findAllForItem(OrderItem $item): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.orderItem = :it')
            ->setParameter('it', $item)
            ->orderBy('m.version', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return MarkingAsset[] Assets pending review, latest per item, for admin dashboard */
    public function findPendingForReview(int $limit = 50): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.status = :pending')
            ->setParameter('pending', MarkingStatus::PENDING)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countPendingForReview(): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.status = :pending')
            ->setParameter('pending', MarkingStatus::PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function hasAnyPendingForOrder(Order $order): bool
    {
        $c = (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->join('m.orderItem', 'i')
            ->andWhere('i.order = :o')
            ->andWhere('m.status = :pending')
            ->setParameter('o', $order)
            ->setParameter('pending', MarkingStatus::PENDING)
            ->getQuery()
            ->getSingleScalarResult();
        return $c > 0;
    }
}
