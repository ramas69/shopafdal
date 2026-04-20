<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\CompanyPrice;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompanyPrice>
 */
class CompanyPriceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompanyPrice::class);
    }

    public function findForCompanyAndProduct(Company $company, Product $product): ?CompanyPrice
    {
        return $this->findOneBy(['company' => $company, 'product' => $product]);
    }

    /** @return CompanyPrice[] */
    public function findForCompany(Company $company): array
    {
        return $this->createQueryBuilder('cp')
            ->andWhere('cp.company = :c')
            ->setParameter('c', $company)
            ->getQuery()
            ->getResult();
    }
}
