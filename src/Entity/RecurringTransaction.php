<?php

namespace App\Entity;

use App\Enum\TransactionType;
use App\Repository\RecurringTransactionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * A template that the scheduler materialises into real Transactions on a cadence.
 */
#[ORM\Entity(repositoryClass: RecurringTransactionRepository::class)]
class RecurringTransaction
{
    public const FREQ_DAILY = 'daily';
    public const FREQ_WEEKLY = 'weekly';
    public const FREQ_MONTHLY = 'monthly';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['recurring:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['recurring:read'])]
    private Account $account;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['recurring:read'])]
    private ?Category $category = null;

    #[ORM\Column(enumType: TransactionType::class)]
    #[Groups(['recurring:read'])]
    private TransactionType $type;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2)]
    #[Groups(['recurring:read'])]
    private string $amount;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['recurring:read'])]
    private ?string $description = null;

    #[ORM\Column(length: 10)]
    #[Groups(['recurring:read'])]
    private string $frequency = self::FREQ_MONTHLY;

    #[ORM\Column(type: 'date_immutable')]
    #[Groups(['recurring:read'])]
    private \DateTimeImmutable $nextRunOn;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Groups(['recurring:read'])]
    private ?\DateTimeImmutable $endsOn = null;

    #[ORM\Column]
    #[Groups(['recurring:read'])]
    private bool $active = true;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->nextRunOn = new \DateTimeImmutable();
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

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function setAccount(Account $account): static
    {
        $this->account = $account;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getType(): TransactionType
    {
        return $this->type;
    }

    public function setType(TransactionType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getFrequency(): string
    {
        return $this->frequency;
    }

    public function setFrequency(string $frequency): static
    {
        $this->frequency = $frequency;

        return $this;
    }

    public function getNextRunOn(): \DateTimeImmutable
    {
        return $this->nextRunOn;
    }

    public function setNextRunOn(\DateTimeImmutable $nextRunOn): static
    {
        $this->nextRunOn = $nextRunOn;

        return $this;
    }

    public function getEndsOn(): ?\DateTimeImmutable
    {
        return $this->endsOn;
    }

    public function setEndsOn(?\DateTimeImmutable $endsOn): static
    {
        $this->endsOn = $endsOn;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    /** Advance nextRunOn by one cadence step. */
    public function advance(): void
    {
        $this->nextRunOn = match ($this->frequency) {
            self::FREQ_DAILY => $this->nextRunOn->modify('+1 day'),
            self::FREQ_WEEKLY => $this->nextRunOn->modify('+1 week'),
            default => $this->nextRunOn->modify('+1 month'),
        };

        if ($this->endsOn !== null && $this->nextRunOn > $this->endsOn) {
            $this->active = false;
        }
    }
}
