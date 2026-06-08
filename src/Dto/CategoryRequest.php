<?php

namespace App\Dto;

use App\Enum\CategoryType;
use Symfony\Component\Validator\Constraints as Assert;

class CategoryRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $name = '';

    #[Assert\NotNull]
    public ?CategoryType $type = null;

    #[Assert\Regex(pattern: '/^#[0-9a-fA-F]{6}$/', message: 'Must be a hex colour like #ef4444.')]
    public ?string $color = null;
}
