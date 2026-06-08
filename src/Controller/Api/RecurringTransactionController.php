<?php

namespace App\Controller\Api;

use App\Dto\RecurringTransactionRequest;
use App\Entity\Account;
use App\Entity\Category;
use App\Entity\RecurringTransaction;
use App\Repository\AccountRepository;
use App\Repository\CategoryRepository;
use App\Repository\RecurringTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/recurring')]
#[OA\Tag(name: 'Recurring')]
class RecurringTransactionController extends AbstractApiController
{
    public function __construct(
        SerializerInterface $serializer,
        private readonly EntityManagerInterface $em,
        private readonly RecurringTransactionRepository $recurring,
        private readonly AccountRepository $accounts,
        private readonly CategoryRepository $categories,
    ) {
        parent::__construct($serializer);
    }

    #[Route('', name: 'api_recurring_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $items = $this->recurring->findBy(['owner' => $this->currentUser()->getId()], ['nextRunOn' => 'ASC']);

        return $this->serialized($items, ['recurring:read']);
    }

    #[Route('', name: 'api_recurring_create', methods: ['POST'])]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: RecurringTransactionRequest::class)))]
    public function create(#[MapRequestPayload] RecurringTransactionRequest $dto): JsonResponse
    {
        $item = new RecurringTransaction();
        $item->setOwner($this->currentUser());
        $this->apply($item, $dto);

        $this->em->persist($item);
        $this->em->flush();

        return $this->serialized($item, ['recurring:read'], JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_recurring_update', methods: ['PUT'])]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: RecurringTransactionRequest::class)))]
    public function update(string $id, #[MapRequestPayload] RecurringTransactionRequest $dto): JsonResponse
    {
        $item = $this->ownedRecurring($id);
        $this->apply($item, $dto);
        $this->em->flush();

        return $this->serialized($item, ['recurring:read']);
    }

    #[Route('/{id}', name: 'api_recurring_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $item = $this->ownedRecurring($id);
        $this->em->remove($item);
        $this->em->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function apply(RecurringTransaction $item, RecurringTransactionRequest $dto): void
    {
        $item->setAccount($this->ownedAccount($dto->accountId));
        $item->setCategory($dto->categoryId !== null ? $this->ownedCategory($dto->categoryId) : null);
        $item->setType($dto->type);
        $item->setAmount($dto->amount);
        $item->setDescription($dto->description);
        $item->setFrequency($dto->frequency);
        $item->setNextRunOn(new \DateTimeImmutable($dto->nextRunOn));
        $item->setEndsOn($dto->endsOn !== null ? new \DateTimeImmutable($dto->endsOn) : null);
    }

    private function ownedRecurring(string $id): RecurringTransaction
    {
        $item = $this->recurring->find($id);
        if ($item === null || $item->getOwner() !== $this->currentUser()) {
            throw $this->createNotFoundException('Recurring transaction not found.');
        }

        return $item;
    }

    private function ownedAccount(string $id): Account
    {
        $account = $this->accounts->find($id);
        if ($account === null || $account->getOwner() !== $this->currentUser()) {
            throw $this->createNotFoundException('Account not found.');
        }

        return $account;
    }

    private function ownedCategory(string $id): Category
    {
        $category = $this->categories->find($id);
        if ($category === null || $category->getOwner() !== $this->currentUser()) {
            throw $this->createNotFoundException('Category not found.');
        }

        return $category;
    }
}
