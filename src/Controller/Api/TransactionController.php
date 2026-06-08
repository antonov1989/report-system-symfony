<?php

namespace App\Controller\Api;

use App\Dto\TransactionRequest;
use App\Entity\Account;
use App\Entity\Category;
use App\Entity\Transaction;
use App\Repository\AccountRepository;
use App\Repository\CategoryRepository;
use App\Repository\TransactionRepository;
use App\Service\BudgetMonitor;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/transactions')]
#[OA\Tag(name: 'Transactions')]
class TransactionController extends AbstractApiController
{
    public function __construct(
        SerializerInterface $serializer,
        private readonly EntityManagerInterface $em,
        private readonly TransactionRepository $transactions,
        private readonly AccountRepository $accounts,
        private readonly CategoryRepository $categories,
        private readonly BudgetMonitor $budgetMonitor,
    ) {
        parent::__construct($serializer);
    }

    #[Route('', name: 'api_transactions_list', methods: ['GET'])]
    #[OA\Parameter(name: 'accountId', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'categoryId', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'type', in: 'query', schema: new OA\Schema(type: 'string', enum: ['income', 'expense', 'transfer']))]
    #[OA\Parameter(name: 'from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer', default: 25))]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(100, max(1, $request->query->getInt('perPage', 25)));

        $result = $this->transactions->search(
            $this->currentUser(),
            $request->query->all(),
            $page,
            $perPage,
        );

        $json = $this->serializer->serialize($result['items'], 'json', ['groups' => ['transaction:read']]);

        return new JsonResponse([
            'data' => json_decode($json, true),
            'meta' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'pages' => (int) ceil($result['total'] / $result['perPage']),
            ],
        ]);
    }

    #[Route('/{id}', name: 'api_transactions_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        return $this->serialized($this->ownedTransaction($id), ['transaction:read']);
    }

    #[Route('', name: 'api_transactions_create', methods: ['POST'])]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: TransactionRequest::class)))]
    public function create(#[MapRequestPayload] TransactionRequest $dto): JsonResponse
    {
        $transaction = new Transaction();
        $this->apply($transaction, $dto);

        $this->em->persist($transaction);
        $this->em->flush();

        $this->budgetMonitor->checkForTransaction($transaction);

        return $this->serialized($transaction, ['transaction:read'], JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_transactions_update', methods: ['PUT'])]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: TransactionRequest::class)))]
    public function update(string $id, #[MapRequestPayload] TransactionRequest $dto): JsonResponse
    {
        $transaction = $this->ownedTransaction($id);
        $this->apply($transaction, $dto);
        $this->em->flush();

        $this->budgetMonitor->checkForTransaction($transaction);

        return $this->serialized($transaction, ['transaction:read']);
    }

    #[Route('/{id}', name: 'api_transactions_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $transaction = $this->ownedTransaction($id);
        $this->em->remove($transaction);
        $this->em->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function apply(Transaction $transaction, TransactionRequest $dto): void
    {
        $transaction->setAccount($this->ownedAccountById($dto->accountId));
        $transaction->setCategory($dto->categoryId !== null ? $this->ownedCategoryById($dto->categoryId) : null);
        $transaction->setType($dto->type);
        $transaction->setAmount($dto->amount);
        $transaction->setDescription($dto->description);
        $transaction->setOccurredOn(
            $dto->occurredOn !== null ? new \DateTimeImmutable($dto->occurredOn) : new \DateTimeImmutable('today'),
        );
    }

    private function ownedTransaction(string $id): Transaction
    {
        $transaction = $this->transactions->find($id);
        if ($transaction === null || $transaction->getAccount()->getOwner() !== $this->currentUser()) {
            throw $this->createNotFoundException('Transaction not found.');
        }

        return $transaction;
    }

    private function ownedAccountById(string $id): Account
    {
        $account = $this->accounts->find($id);
        if ($account === null || $account->getOwner() !== $this->currentUser()) {
            throw $this->createNotFoundException('Account not found.');
        }

        return $account;
    }

    private function ownedCategoryById(string $id): Category
    {
        $category = $this->categories->find($id);
        if ($category === null || $category->getOwner() !== $this->currentUser()) {
            throw $this->createNotFoundException('Category not found.');
        }

        return $category;
    }
}
