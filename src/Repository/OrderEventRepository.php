<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\OrderEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderEvent>
 */
class OrderEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderEvent::class);
    }

    /** @return OrderEvent[] */
    public function findForOrder(Order $order): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.order = :order')
            ->setParameter('order', $order)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
