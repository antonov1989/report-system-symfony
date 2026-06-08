<?php

namespace App\Service;

use App\Entity\Transaction;
use App\Enum\TransactionType;
use App\Message\BudgetExceededNotification;
use App\Repository\BudgetRepository;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * After an expense is recorded, checks whether its category's monthly budget
 * has been exceeded and — once per budget — queues an email alert.
 */
class BudgetMonitor
{
    public function __construct(
        private readonly BudgetRepository $budgets,
        private readonly TransactionRepository $transactions,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function checkForTransaction(Transaction $transaction): void
    {
        if ($transaction->getType() !== TransactionType::EXPENSE || $transaction->getCategory() === null) {
            return;
        }

        $category = $transaction->getCategory();
        $monthStart = $transaction->getOccurredOn()->modify('first day of this month')->setTime(0, 0);

        $budget = $this->budgets->findOneBy([
            'category' => $category->getId(),
            'period' => $monthStart,
        ]);

        if ($budget === null || $budget->isAlertSent()) {
            return;
        }

        $spent = $this->transactions->spentForCategoryInMonth($category, $monthStart);
        if ($spent <= (float) $budget->getLimitAmount()) {
            return;
        }

        $budget->setAlertSent(true);
        $this->em->flush();

        $this->bus->dispatch(new BudgetExceededNotification((string) $budget->getId()));
    }
}
