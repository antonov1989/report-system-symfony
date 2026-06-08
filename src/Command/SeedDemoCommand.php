<?php

namespace App\Command;

use App\Entity\Account;
use App\Entity\Budget;
use App\Entity\Category;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\CategoryType;
use App\Enum\TransactionType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Seeds a demo account with six months of realistic data so the dashboard
 * and API have something to show. Re-running it resets the demo user.
 */
#[AsCommand(name: 'app:demo:seed', description: 'Seed a demo user with sample finance data')]
class SeedDemoCommand extends Command
{
    private const EMAIL = 'demo@example.com';
    private const PASSWORD = 'secret123';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($existing = $this->users->findOneBy(['email' => self::EMAIL])) {
            $this->purge($existing);
            $io->note('Removed existing demo user and their data.');
        }

        $user = new User();
        $user->setEmail(self::EMAIL);
        $user->setName('Demo User');
        $user->setDefaultCurrency('EUR');
        $user->setPassword($this->hasher->hashPassword($user, self::PASSWORD));
        $this->em->persist($user);

        $checking = (new Account())->setOwner($user)->setName('Checking')->setCurrency('EUR')->setOpeningBalance('2500.00');
        $savings = (new Account())->setOwner($user)->setName('Savings')->setCurrency('EUR')->setOpeningBalance('8000.00');
        $this->em->persist($checking);
        $this->em->persist($savings);

        $catDefs = [
            ['Salary', CategoryType::INCOME, '#22c55e'],
            ['Groceries', CategoryType::EXPENSE, '#ef4444'],
            ['Rent', CategoryType::EXPENSE, '#f59e0b'],
            ['Transport', CategoryType::EXPENSE, '#3b82f6'],
            ['Dining', CategoryType::EXPENSE, '#8b5cf6'],
            ['Entertainment', CategoryType::EXPENSE, '#ec4899'],
        ];
        $cats = [];
        foreach ($catDefs as [$name, $type, $color]) {
            $cats[$name] = (new Category())->setOwner($user)->setName($name)->setType($type)->setColor($color);
            $this->em->persist($cats[$name]);
        }

        // Six months of transactions.
        $expensePlan = [
            'Groceries' => [180, 320],
            'Rent' => [950, 950],
            'Transport' => [40, 90],
            'Dining' => [60, 200],
            'Entertainment' => [20, 120],
        ];

        $count = 0;
        for ($m = 5; $m >= 0; --$m) {
            $monthStart = (new \DateTimeImmutable('first day of this month'))->modify("-$m months");

            // Monthly salary on the 1st.
            $this->em->persist((new Transaction())
                ->setAccount($checking)->setCategory($cats['Salary'])->setType(TransactionType::INCOME)
                ->setAmount('3200.00')->setDescription('Monthly salary')
                ->setOccurredOn($monthStart));
            ++$count;

            foreach ($expensePlan as $cat => [$min, $max]) {
                $occurrences = $cat === 'Rent' ? 1 : random_int(2, 5);
                for ($i = 0; $i < $occurrences; ++$i) {
                    $amount = $cat === 'Rent' ? $min : random_int($min, $max) / $occurrences;
                    $day = random_int(1, 27);
                    $this->em->persist((new Transaction())
                        ->setAccount($checking)->setCategory($cats[$cat])->setType(TransactionType::EXPENSE)
                        ->setAmount(number_format($amount, 2, '.', ''))
                        ->setDescription($cat . ' expense')
                        ->setOccurredOn($monthStart->modify('+' . $day . ' days')));
                    ++$count;
                }
            }
        }

        // Current-month budgets.
        $thisMonth = new \DateTimeImmutable('first day of this month 00:00:00');
        foreach (['Groceries' => '300.00', 'Dining' => '150.00', 'Entertainment' => '100.00'] as $cat => $limit) {
            $this->em->persist((new Budget())
                ->setOwner($user)->setCategory($cats[$cat])->setLimitAmount($limit)->setPeriod($thisMonth));
        }

        $this->em->flush();

        $io->success(sprintf('Seeded demo user with %d transactions.', $count));
        $io->writeln(sprintf('  Login: <info>%s</info> / <info>%s</info>', self::EMAIL, self::PASSWORD));

        return Command::SUCCESS;
    }

    /**
     * Delete a user's dependent data (no DB-level cascades are defined) and the user.
     */
    private function purge(User $user): void
    {
        $conn = $this->em->getConnection();
        $uuid = (string) $user->getId();

        // Transactions belong to the user's accounts; delete them first.
        $conn->executeStatement(
            'DELETE FROM transaction WHERE account_id IN (SELECT id FROM account WHERE owner_id = ?)',
            [$uuid],
        );
        foreach (['recurring_transaction', 'budget', 'category', 'account'] as $table) {
            $conn->executeStatement("DELETE FROM $table WHERE owner_id = ?", [$uuid]);
        }
        $conn->executeStatement('DELETE FROM "user" WHERE id = ?', [$uuid]);

        $this->em->clear();
    }
}
