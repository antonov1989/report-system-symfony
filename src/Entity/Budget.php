<?php

namespace App\Entity;

use App\Repository\BudgetRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * A monthly spending limit for a category. `period` is the first day of the month.
 */
#[ORM\Entity(repositoryClass: BudgetRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_budget_cat_period', columns: ['category_id', 'period'])]
class Budget
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['budget:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['budget:read'])]
    private Category $category;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2)]
    #[Groups(['budget:read'])]
    private string $limitAmount;

    /** First day of the budgeted month. */
    #[ORM\Column(type: 'date_immutable')]
    #[Groups(['budget:read'])]
    private \DateTimeImmutable $period;

    /** Set once we have emailed the user that this budget was exceeded. */
    #[ORM\Column]
    private bool $alertSent = false;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getCategory(): Category
    {
        return $this->category;
    }

    public function setCategory(Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getLimitAmount(): string
    {
        return $this->limitAmount;
    }

    public function setLimitAmount(string $limitAmount): static
    {
        $this->limitAmount = $limitAmount;

        return $this;
    }

    public function getPeriod(): \DateTimeImmutable
    {
        return $this->period;
    }

    public function setPeriod(\DateTimeImmutable $period): static
    {
        $this->period = $period;

        return $this;
    }

    public function isAlertSent(): bool
    {
        return $this->alertSent;
    }

    public function setAlertSent(bool $alertSent): static
    {
        $this->alertSent = $alertSent;

        return $this;
    }
}
