<?php

namespace App\Repository;

use App\Entity\ScreenOffPeriod;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ScreenOffPeriod>
 */
class ScreenOffPeriodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScreenOffPeriod::class);
    }

    /**
     * @return ScreenOffPeriod[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ScreenOffPeriod[]
     */
    public function findEnabled(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.enabled = true')
            ->getQuery()
            ->getResult();
    }
}
