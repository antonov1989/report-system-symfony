<?php

namespace App\Enum;

enum TransactionType: string
{
    case INCOME = 'income';
    case EXPENSE = 'expense';
    case TRANSFER = 'transfer';

    /**
     * Sign applied to an account balance: income adds, expense subtracts.
     * Transfers are handled per-leg by the service layer.
     */
    public function sign(): int
    {
        return match ($this) {
            self::INCOME => 1,
            self::EXPENSE => -1,
            self::TRANSFER => -1,
        };
    }
}
