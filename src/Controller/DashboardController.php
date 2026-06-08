<?php

namespace App\Controller;

use App\Entity\Account;
use App\Entity\User;
use App\Repository\AccountRepository;
use App\Repository\BudgetRepository;
use App\Repository\TransactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly TransactionRepository $transactions,
        private readonly BudgetRepository $budgets,
    ) {
    }

    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $monthStart = new \DateTimeImmutable('first day of this month 00:00:00');

        $accounts = $this->accounts->findByOwner($user);
        $netWorth = 0.0;
        $accountRows = [];
        foreach ($accounts as $account) {
            $balance = $this->accounts->currentBalance($account);
            $netWorth += $balance;
            $accountRows[] = ['account' => $account, 'balance' => $balance];
        }

        $monthly = $this->transactions->monthlyTotals($user, 6);
        $byCategory = $this->transactions->spendingByCategory($user, $monthStart);

        $current = end($monthly) ?: ['income' => 0.0, 'expense' => 0.0];

        $budgets = array_map(function ($b) {
            $limit = (float) $b->getLimitAmount();
            $spent = $this->transactions->spentForCategoryInMonth($b->getCategory(), $b->getPeriod());

            return [
                'name' => $b->getCategory()->getName(),
                'color' => $b->getCategory()->getColor(),
                'limit' => $limit,
                'spent' => $spent,
                'progress' => $limit > 0 ? min(100, round($spent / $limit * 100, 1)) : 0,
                'exceeded' => $spent > $limit,
            ];
        }, $this->budgets->findByOwnerAndPeriod($user, $monthStart));

        return $this->render('dashboard/index.html.twig', [
            'netWorth' => round($netWorth, 2),
            'income' => $current['income'],
            'expense' => $current['expense'],
            'currency' => $user->getDefaultCurrency(),
            'accountRows' => $accountRows,
            'monthly' => $monthly,
            'byCategory' => $byCategory,
            'budgets' => $budgets,
            'month' => $monthStart->format('F Y'),
        ]);
    }
}
