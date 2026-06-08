<?php

namespace App\MessageHandler;

use App\Message\BudgetExceededNotification;
use App\Repository\BudgetRepository;
use App\Repository\TransactionRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
final class BudgetExceededNotificationHandler
{
    public function __construct(
        private readonly BudgetRepository $budgets,
        private readonly TransactionRepository $transactions,
        private readonly MailerInterface $mailer,
        private readonly string $mailFrom = 'alerts@finance-tracker.local',
    ) {
    }

    public function __invoke(BudgetExceededNotification $message): void
    {
        $budget = $this->budgets->find($message->budgetId);
        if ($budget === null) {
            return;
        }

        $spent = $this->transactions->spentForCategoryInMonth($budget->getCategory(), $budget->getPeriod());

        $email = (new Email())
            ->from($this->mailFrom)
            ->to($budget->getOwner()->getEmail())
            ->subject(sprintf('Budget exceeded: %s', $budget->getCategory()->getName()))
            ->text(sprintf(
                "Heads up! Your \"%s\" budget for %s has been exceeded.\n\nLimit:  %.2f\nSpent:  %.2f\nOver by: %.2f\n",
                $budget->getCategory()->getName(),
                $budget->getPeriod()->format('F Y'),
                (float) $budget->getLimitAmount(),
                $spent,
                $spent - (float) $budget->getLimitAmount(),
            ));

        $this->mailer->send($email);
    }
}
