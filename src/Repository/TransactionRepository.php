<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * Paginated, filterable list scoped to a user.
     *
     * @param array{accountId?:string,categoryId?:string,type?:string,from?:string,to?:string} $filters
     * @return array{items: Transaction[], total: int, page: int, perPage: int}
     */
    public function search(User $owner, array $filters, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('t')
            ->join('t.account', 'a')
            ->andWhere('a.owner = :owner')
            ->setParameter('owner', $owner->getId(), 'uuid')
            ->orderBy('t.occurredOn', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC');

        if (!empty($filters['accountId'])) {
            $qb->andWhere('a.id = :accId')->setParameter('accId', $filters['accountId'], 'uuid');
        }
        if (!empty($filters['categoryId'])) {
            $qb->andWhere('t.category = :catId')->setParameter('catId', $filters['categoryId'], 'uuid');
        }
        if (!empty($filters['type'])) {
            $qb->andWhere('t.type = :type')->setParameter('type', $filters['type']);
        }
        if (!empty($filters['from'])) {
            $qb->andWhere('t.occurredOn >= :from')->setParameter('from', new \DateTimeImmutable($filters['from']));
        }
        if (!empty($filters['to'])) {
            $qb->andWhere('t.occurredOn <= :to')->setParameter('to', new \DateTimeImmutable($filters['to']));
        }

        $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage);

        $paginator = new Paginator($qb, fetchJoinCollection: false);

        return [
            'items' => iterator_to_array($paginator),
            'total' => count($paginator),
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * Total spent (expense) for a category within a month.
     */
    public function spentForCategoryInMonth(Category $category, \DateTimeImmutable $monthStart): float
    {
        $monthEnd = $monthStart->modify('first day of next month');

        $sum = $this->createQueryBuilder('t')
            ->select('COALESCE(SUM(t.amount), 0)')
            ->andWhere('t.category = :cat')
            ->andWhere('t.type = :expense')
            ->andWhere('t.occurredOn >= :start')
            ->andWhere('t.occurredOn < :end')
            ->setParameter('cat', $category->getId(), 'uuid')
            ->setParameter('expense', 'expense')
            ->setParameter('start', $monthStart)
            ->setParameter('end', $monthEnd)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $sum;
    }

    /**
     * Spending grouped by category for a month (for the dashboard pie chart).
     *
     * @return array<int, array{categoryId: ?string, name: string, color: ?string, total: float}>
     */
    public function spendingByCategory(User $owner, \DateTimeImmutable $monthStart): array
    {
        $monthEnd = $monthStart->modify('first day of next month');

        $rows = $this->createQueryBuilder('t')
            ->select('c.id AS categoryId', 'c.name AS name', 'c.color AS color', 'SUM(t.amount) AS total')
            ->join('t.account', 'a')
            ->leftJoin('t.category', 'c')
            ->andWhere('a.owner = :owner')
            ->andWhere('t.type = :expense')
            ->andWhere('t.occurredOn >= :start')
            ->andWhere('t.occurredOn < :end')
            ->setParameter('owner', $owner->getId(), 'uuid')
            ->setParameter('expense', 'expense')
            ->setParameter('start', $monthStart)
            ->setParameter('end', $monthEnd)
            ->groupBy('c.id', 'c.name', 'c.color')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $r): array => [
            'categoryId' => $r['categoryId'] !== null ? (string) $r['categoryId'] : null,
            'name' => $r['name'] ?? 'Uncategorised',
            'color' => $r['color'] ?? null,
            'total' => (float) $r['total'],
        ], $rows);
    }

    /**
     * Monthly income vs expense totals over the last N months (for the bar chart).
     *
     * @return array<int, array{month: string, income: float, expense: float}>
     */
    public function monthlyTotals(User $owner, int $months = 6): array
    {
        $start = (new \DateTimeImmutable('first day of this month'))->modify(sprintf('-%d months', $months - 1));

        // Seed every bucket so months with no activity still appear.
        $buckets = [];
        for ($i = 0; $i < $months; ++$i) {
            $key = $start->modify(sprintf('+%d months', $i))->format('Y-m');
            $buckets[$key] = ['month' => $key, 'income' => 0.0, 'expense' => 0.0];
        }

        $rows = $this->createQueryBuilder('t')
            ->select('t.type AS type', 't.amount AS amount', 't.occurredOn AS occurredOn')
            ->join('t.account', 'a')
            ->andWhere('a.owner = :owner')
            ->andWhere('t.occurredOn >= :start')
            ->setParameter('owner', $owner->getId(), 'uuid')
            ->setParameter('start', $start)
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as $r) {
            $key = $r['occurredOn']->format('Y-m');
            if (!isset($buckets[$key])) {
                continue;
            }
            $type = $r['type'] instanceof \App\Enum\TransactionType ? $r['type']->value : (string) $r['type'];
            if ($type === 'income') {
                $buckets[$key]['income'] += (float) $r['amount'];
            } elseif ($type === 'expense') {
                $buckets[$key]['expense'] += (float) $r['amount'];
            }
        }

        return array_values($buckets);
    }
}
