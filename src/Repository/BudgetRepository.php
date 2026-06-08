<?php

namespace App\Repository;

use App\Entity\Budget;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Budget>
 */
class BudgetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Budget::class);
    }

    /**
     * @return Budget[]
     */
    public function findByOwnerAndPeriod(User $owner, \DateTimeImmutable $period): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.owner = :owner')
            ->andWhere('b.period = :period')
            ->setParameter('owner', $owner->getId(), 'uuid')
            ->setParameter('period', $period)
            ->getQuery()
            ->getResult();
    }
}
