<?php

namespace App\Message;

/**
 * Dispatched when a monthly budget is first exceeded. Handled asynchronously
 * to send the user an email alert.
 */
final readonly class BudgetExceededNotification
{
    public function __construct(public string $budgetId)
    {
    }
}
