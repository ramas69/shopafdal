<?php

namespace App\Repository;

use App\Entity\PriceTier;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PriceTier>
 */
class PriceTierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PriceTier::class);
    }

    /** @return PriceTier[] */
    public function findForProduct(Product $product): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.product = :p')
            ->setParameter('p', $product)
            ->orderBy('t.minQty', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
