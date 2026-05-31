<?php

namespace App\Repository;

use App\Entity\Company;
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
    public function findForCompany(Company $company): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.order', 'o')
            ->andWhere('o.company = :company')
            ->setParameter('company', $company)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return OrderDocument[] */
    public function findAllRecent(): array
    {
        return $this->createQueryBuilder('d')
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
