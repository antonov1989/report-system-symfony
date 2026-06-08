<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class BudgetRequest
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $categoryId = '';

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/', message: 'Must be a positive decimal with up to 2 places.')]
    #[Assert\GreaterThan(0)]
    public string $limitAmount = '';

    /** Month in Y-m format, e.g. 2026-06. */
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{4}-\d{2}$/', message: 'Must be a month in Y-m format, e.g. 2026-06.')]
    public string $period = '';
}
