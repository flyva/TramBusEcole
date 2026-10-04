<?php

namespace App\Repository;

use App\Entity\Preset;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Preset>
 */
class PresetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Preset::class);
    }

    /**
     * @return Preset[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds the preset whose recurring weekly schedule currently matches,
     * if any (first match wins).
     */
    public function findScheduledForNow(\DateTimeImmutable $now): ?Preset
    {
        foreach ($this->createQueryBuilder('p')->andWhere('p.enabled = true')->getQuery()->getResult() as $preset) {
            if ($preset->matchesSchedule($now)) {
                return $preset;
            }
        }

        return null;
    }
}
