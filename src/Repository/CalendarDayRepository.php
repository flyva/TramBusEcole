<?php

namespace App\Repository;

use App\Entity\CalendarDay;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CalendarDay>
 */
class CalendarDayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CalendarDay::class);
    }

    /**
     * @return CalendarDay[]
     */
    public function findByDate(\DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.date = :date')
            ->setParameter('date', $date->setTime(0, 0), Types::DATE_IMMUTABLE)
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForDateAndPreset(\DateTimeImmutable $date, int $presetId): ?CalendarDay
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.date = :date')
            ->andWhere('c.preset = :presetId')
            ->setParameter('date', $date->setTime(0, 0), Types::DATE_IMMUTABLE)
            ->setParameter('presetId', $presetId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return array<string, CalendarDay[]> keyed by "Y-m-d"
     */
    public function findForMonth(int $year, int $month): array
    {
        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $end = $start->modify('first day of next month');

        $rows = $this->createQueryBuilder('c')
            ->andWhere('c.date >= :start')
            ->andWhere('c.date < :end')
            ->setParameter('start', $start, Types::DATE_IMMUTABLE)
            ->setParameter('end', $end, Types::DATE_IMMUTABLE)
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();

        $byDate = [];
        foreach ($rows as $row) {
            $byDate[$row->getDate()->format('Y-m-d')][] = $row;
        }

        return $byDate;
    }
}
