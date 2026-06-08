<?php

namespace App\Command;

use App\Entity\Transaction;
use App\Repository\RecurringTransactionRepository;
use App\Service\BudgetMonitor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Materialises due recurring transactions into real transactions.
 * Intended to run daily from cron:  symfony console app:recurring:run
 */
#[AsCommand(
    name: 'app:recurring:run',
    description: 'Generate transactions from due recurring templates',
)]
class RunRecurringTransactionsCommand extends Command
{
    public function __construct(
        private readonly RecurringTransactionRepository $recurring,
        private readonly EntityManagerInterface $em,
        private readonly BudgetMonitor $budgetMonitor,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $today = new \DateTimeImmutable('today');

        $due = $this->recurring->findDue($today);
        $generated = 0;
        $created = [];

        foreach ($due as $template) {
            // Catch up on every occurrence missed since the last run.
            while ($template->isActive() && $template->getNextRunOn() <= $today) {
                $transaction = (new Transaction())
                    ->setAccount($template->getAccount())
                    ->setCategory($template->getCategory())
                    ->setType($template->getType())
                    ->setAmount($template->getAmount())
                    ->setDescription($template->getDescription())
                    ->setOccurredOn($template->getNextRunOn());

                $this->em->persist($transaction);
                $created[] = $transaction;
                ++$generated;

                $template->advance();
            }
        }

        $this->em->flush();

        // Budget alerts run after persistence so spent totals include the new rows.
        foreach ($created as $transaction) {
            $this->budgetMonitor->checkForTransaction($transaction);
        }

        $io->success(sprintf('Generated %d transaction(s) from %d template(s).', $generated, count($due)));

        return Command::SUCCESS;
    }
}
