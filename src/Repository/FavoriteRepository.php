<?php

namespace App\Repository;

use App\Entity\Favorite;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Favorite>
 */
class FavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Favorite::class);
    }

    public function findOneByUserAndProduct(User $user, Product $product): ?Favorite
    {
        return $this->findOneBy(['user' => $user, 'product' => $product]);
    }

    /** @return int[] Product IDs favorited by the user */
    public function findProductIdsForUser(User $user): array
    {
        $rows = $this->createQueryBuilder('f')
            ->select('IDENTITY(f.product) AS pid')
            ->andWhere('f.user = :u')
            ->setParameter('u', $user)
            ->getQuery()
            ->getArrayResult();
        return array_map(fn($r) => (int) $r['pid'], $rows);
    }

    /** @return Favorite[] */
    public function findRecentForUser(User $user): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.user = :u')
            ->setParameter('u', $user)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
