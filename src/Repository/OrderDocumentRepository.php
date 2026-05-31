<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\OrderDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderDocument>
 */
class OrderDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderDocument::class);
    }

    /** @return OrderDocument[] */
    public function findForOrder(Order $order): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.order = :order')
            ->setParameter('order', $order)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
