<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class AccountRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $name = '';

    #[Assert\Length(exactly: 3)]
    public string $currency = 'USD';

    #[Assert\Regex(pattern: '/^-?\d+(\.\d{1,2})?$/', message: 'Must be a decimal with up to 2 places.')]
    public string $openingBalance = '0.00';
}
