<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /** @return Product[] */
    public function findPublished(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :published')
            ->setParameter('published', \App\Enum\ProductStatus::PUBLISHED)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Query builder filtré : produits publiés + accessibles à la company donnée. */
    public function createCatalogueQueryBuilder(Company $company, string $alias = 'p'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->innerJoin($alias.'.allowedCompanies', 'ac')
            ->andWhere($alias.'.status = :published')
            ->andWhere('ac = :company')
            ->setParameter('published', \App\Enum\ProductStatus::PUBLISHED)
            ->setParameter('company', $company);
    }
}
