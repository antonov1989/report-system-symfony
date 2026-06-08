<?php

namespace App\Repository;

use App\Entity\RecurringTransaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RecurringTransaction>
 */
class RecurringTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RecurringTransaction::class);
    }

    /**
     * Active templates whose next run is due on or before the given date.
     *
     * @return RecurringTransaction[]
     */
    public function findDue(\DateTimeImmutable $onOrBefore): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.active = true')
            ->andWhere('r.nextRunOn <= :date')
            ->setParameter('date', $onOrBefore)
            ->getQuery()
            ->getResult();
    }
}
