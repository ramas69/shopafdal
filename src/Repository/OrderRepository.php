<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\Order;
use App\Enum\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /** @return Order[] */
    public function findForCompany(Company $company): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.company = :company')
            ->setParameter('company', $company)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Order[] */
    public function findToProcess(): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('statuses', [OrderStatus::PLACED, OrderStatus::CONFIRMED, OrderStatus::IN_PRODUCTION])
            ->orderBy('o.placedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
