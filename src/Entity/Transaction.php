<?php

namespace App\Entity;

use App\Enum\TransactionType;
use App\Repository\TransactionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Index(fields: ['occurredOn'], name: 'idx_tx_occurred_on')]
class Transaction
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['transaction:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Account::class, inversedBy: 'transactions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['transaction:read'])]
    private Account $account;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['transaction:read'])]
    private ?Category $category = null;

    #[ORM\Column(enumType: TransactionType::class)]
    #[Groups(['transaction:read'])]
    private TransactionType $type;

    /** Always positive; the sign is derived from the type. Major units. */
    #[ORM\Column(type: 'decimal', precision: 14, scale: 2)]
    #[Groups(['transaction:read'])]
    private string $amount;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['transaction:read'])]
    private ?string $description = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Groups(['transaction:read'])]
    private \DateTimeImmutable $occurredOn;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['transaction:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->occurredOn = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
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

    public function getOccurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function setOccurredOn(\DateTimeImmutable $occurredOn): static
    {
        $this->occurredOn = $occurredOn;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Signed amount as a float, for aggregation/display. */
    #[Groups(['transaction:read'])]
    public function getSignedAmount(): float
    {
        return (float) $this->amount * $this->type->sign();
    }
}
