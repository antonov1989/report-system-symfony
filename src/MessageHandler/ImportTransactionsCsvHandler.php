<?php

namespace App\MessageHandler;

use App\Entity\Category;
use App\Entity\Transaction;
use App\Enum\CategoryType;
use App\Enum\TransactionType;
use App\Message\ImportTransactionsCsv;
use App\Repository\AccountRepository;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Reader;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Expected CSV header: date,type,amount,category,description
 * e.g. 2026-06-01,expense,42.50,Groceries,Supermarket
 */
#[AsMessageHandler]
final class ImportTransactionsCsvHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AccountRepository $accounts,
        private readonly CategoryRepository $categories,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ImportTransactionsCsv $message): void
    {
        $user = $this->users->find($message->userId);
        $account = $this->accounts->find($message->accountId);

        if ($user === null || $account === null || $account->getOwner() !== $user) {
            $this->logger->warning('CSV import skipped: user/account not found or not owned.', [
                'userId' => $message->userId,
                'accountId' => $message->accountId,
            ]);
            $this->cleanup($message->filePath);

            return;
        }

        if (!is_file($message->filePath)) {
            $this->logger->warning('CSV import file missing.', ['path' => $message->filePath]);

            return;
        }

        // Index this user's categories by lower-cased name so we can reuse/create them.
        $categoryByName = [];
        foreach ($this->categories->findByOwner($user) as $cat) {
            $categoryByName[mb_strtolower($cat->getName())] = $cat;
        }

        $reader = Reader::createFromPath($message->filePath, 'r');
        $reader->setHeaderOffset(0);

        $imported = 0;
        $skipped = 0;

        foreach ($reader->getRecords() as $i => $row) {
            $type = TransactionType::tryFrom(strtolower(trim((string) ($row['type'] ?? ''))));
            $amount = trim((string) ($row['amount'] ?? ''));

            if ($type === null || !preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
                ++$skipped;
                continue;
            }

            $category = null;
            $catName = trim((string) ($row['category'] ?? ''));
            if ($catName !== '') {
                $key = mb_strtolower($catName);
                $category = $categoryByName[$key] ?? null;
                if ($category === null) {
                    $category = (new Category())
                        ->setOwner($user)
                        ->setName($catName)
                        ->setType($type === TransactionType::INCOME ? CategoryType::INCOME : CategoryType::EXPENSE);
                    $this->em->persist($category);
                    $categoryByName[$key] = $category;
                }
            }

            $date = $this->parseDate((string) ($row['date'] ?? ''));

            $transaction = (new Transaction())
                ->setAccount($account)
                ->setCategory($category)
                ->setType($type)
                ->setAmount($amount)
                ->setDescription(($row['description'] ?? null) ?: null)
                ->setOccurredOn($date);

            $this->em->persist($transaction);
            ++$imported;

            // Flush in batches so a large file doesn't build one giant unit of work.
            if (($imported % 200) === 0) {
                $this->em->flush();
            }
        }

        $this->em->flush();
        $this->cleanup($message->filePath);

        $this->logger->info('CSV import finished.', ['imported' => $imported, 'skipped' => $skipped]);
    }

    private function parseDate(string $raw): \DateTimeImmutable
    {
        $raw = trim($raw);
        try {
            return $raw !== '' ? new \DateTimeImmutable($raw) : new \DateTimeImmutable('today');
        } catch (\Exception) {
            return new \DateTimeImmutable('today');
        }
    }

    private function cleanup(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
