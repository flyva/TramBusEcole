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
    public function __construct(
        ManagerRegistry $registry,
        private readonly CalendarDayRepository $calendarDayRepository,
    ) {
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
     * The preset scheduled for right now, per the calendar day assigned to
     * today and that preset's own time window - or null (normal board).
     */
    public function findScheduledForNow(\DateTimeImmutable $now): ?Preset
    {
        $calendarDay = $this->calendarDayRepository->findOneByDate($now);
        if ($calendarDay === null) {
            return null;
        }

        $preset = $calendarDay->getPreset();

        return $preset->matchesTimeWindow($now) ? $preset : null;
    }
}
