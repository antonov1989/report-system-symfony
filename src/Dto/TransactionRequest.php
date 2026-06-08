<?php

namespace App\Dto;

use App\Enum\TransactionType;
use Symfony\Component\Validator\Constraints as Assert;

class TransactionRequest
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $accountId = '';

    #[Assert\Uuid]
    public ?string $categoryId = null;

    #[Assert\NotNull]
    public ?TransactionType $type = null;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/', message: 'Must be a positive decimal with up to 2 places.')]
    #[Assert\GreaterThan(0)]
    public string $amount = '';

    #[Assert\Length(max: 255)]
    public ?string $description = null;

    /** ISO date (Y-m-d). Defaults to today when omitted. */
    #[Assert\Date]
    public ?string $occurredOn = null;
}
