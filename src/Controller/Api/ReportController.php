<?php

namespace App\Controller\Api;

use App\Repository\AccountRepository;
use App\Repository\TransactionRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/reports')]
#[OA\Tag(name: 'Reports')]
class ReportController extends AbstractApiController
{
    public function __construct(
        SerializerInterface $serializer,
        private readonly TransactionRepository $transactions,
        private readonly AccountRepository $accounts,
    ) {
        parent::__construct($serializer);
    }

    /**
     * Net worth + this month's income/expense totals.
     */
    #[Route('/summary', name: 'api_reports_summary', methods: ['GET'])]
    public function summary(): JsonResponse
    {
        $user = $this->currentUser();
        $netWorth = 0.0;
        foreach ($this->accounts->findByOwner($user) as $account) {
            $netWorth += $this->accounts->currentBalance($account);
        }

        $monthStart = new \DateTimeImmutable('first day of this month 00:00:00');
        $monthly = $this->transactions->monthlyTotals($user, 1);
        $current = $monthly[array_key_last($monthly)] ?? ['income' => 0.0, 'expense' => 0.0];

        return new JsonResponse([
            'netWorth' => round($netWorth, 2),
            'month' => $monthStart->format('Y-m'),
            'income' => $current['income'],
            'expense' => $current['expense'],
            'net' => round($current['income'] - $current['expense'], 2),
        ]);
    }

    /**
     * Spending grouped by category for a month (pie chart).
     */
    #[Route('/by-category', name: 'api_reports_by_category', methods: ['GET'])]
    #[OA\Parameter(name: 'period', in: 'query', description: 'Month Y-m', schema: new OA\Schema(type: 'string'))]
    public function byCategory(Request $request): JsonResponse
    {
        $period = $this->resolvePeriod($request->query->get('period'));

        return new JsonResponse(
            $this->transactions->spendingByCategory($this->currentUser(), $period),
        );
    }

    /**
     * Income vs expense per month over the last N months (bar chart).
     */
    #[Route('/monthly', name: 'api_reports_monthly', methods: ['GET'])]
    #[OA\Parameter(name: 'months', in: 'query', schema: new OA\Schema(type: 'integer', default: 6))]
    public function monthly(Request $request): JsonResponse
    {
        $months = min(24, max(1, $request->query->getInt('months', 6)));

        return new JsonResponse(
            $this->transactions->monthlyTotals($this->currentUser(), $months),
        );
    }

    private function resolvePeriod(?string $raw): \DateTimeImmutable
    {
        if (is_string($raw) && preg_match('/^\d{4}-\d{2}$/', $raw)) {
            return (new \DateTimeImmutable($raw . '-01'))->setTime(0, 0);
        }

        return new \DateTimeImmutable('first day of this month 00:00:00');
    }
}
