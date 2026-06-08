<?php

namespace App\Message;

/**
 * Dispatched when a user uploads a CSV of transactions. Handled asynchronously
 * because a large file may contain thousands of rows.
 */
final readonly class ImportTransactionsCsv
{
    public function __construct(
        public string $userId,
        public string $accountId,
        public string $filePath,
    ) {
    }
}
