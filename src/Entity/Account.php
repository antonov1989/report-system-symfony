<?php

namespace App\Entity;

use App\Repository\AccountRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
class Account
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['account:read', 'transaction:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'accounts')]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    #[ORM\Column(length: 100)]
    #[Groups(['account:read', 'transaction:read'])]
    private string $name;

    #[ORM\Column(length: 3)]
    #[Groups(['account:read'])]
    private string $currency = 'USD';

    /** Opening balance in major units (e.g. dollars), stored as decimal string. */
    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, options: ['default' => '0.00'])]
    #[Groups(['account:read'])]
    private string $openingBalance = '0.00';

    #[ORM\Column]
    #[Groups(['account:read'])]
    private bool $archived = false;

    /** @var Collection<int, Transaction> */
    #[ORM\OneToMany(targetEntity: Transaction::class, mappedBy: 'account', orphanRemoval: true)]
    private Collection $transactions;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->transactions = new ArrayCollection();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getOpeningBalance(): string
    {
        return $this->openingBalance;
    }

    public function setOpeningBalance(string $openingBalance): static
    {
        $this->openingBalance = $openingBalance;

        return $this;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function setArchived(bool $archived): static
    {
        $this->archived = $archived;

        return $this;
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }
}
