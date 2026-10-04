<?php

namespace App\Repository;

use App\Entity\CalendarDay;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

    public function findOneByDate(\DateTimeImmutable $date): ?CalendarDay
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.date = :date')
            ->setParameter('date', $date->setTime(0, 0))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return array<string, CalendarDay> keyed by "Y-m-d"
     */
    public function findForMonth(int $year, int $month): array
    {
        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $end = $start->modify('first day of next month');

        $rows = $this->createQueryBuilder('c')
            ->andWhere('c.date >= :start')
            ->andWhere('c.date < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();

        $byDate = [];
        foreach ($rows as $row) {
            $byDate[$row->getDate()->format('Y-m-d')] = $row;
        }

        return $byDate;
    }
}
