<?php

namespace App\Repository;

use App\Entity\Account;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Account>
 */
class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    /**
     * @return Account[]
     */
    public function findByOwner(User $owner): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.owner = :owner')
            ->setParameter('owner', $owner->getId(), 'uuid')
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Current balance = opening balance + sum of signed transactions.
     */
    public function currentBalance(Account $account): float
    {
        $sum = $this->getEntityManager()->createQuery(
            'SELECT COALESCE(SUM(
                CASE WHEN t.type = :income THEN t.amount ELSE -t.amount END
            ), 0)
             FROM App\Entity\Transaction t
             WHERE t.account = :account'
        )
            ->setParameter('income', 'income')
            ->setParameter('account', $account->getId(), 'uuid')
            ->getSingleScalarResult();

        return (float) $account->getOpeningBalance() + (float) $sum;
    }
}
