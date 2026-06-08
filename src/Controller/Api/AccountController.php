<?php

namespace App\Controller\Api;

use App\Dto\AccountRequest;
use App\Entity\Account;
use App\Repository\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/accounts')]
#[OA\Tag(name: 'Accounts')]
class AccountController extends AbstractApiController
{
    public function __construct(
        SerializerInterface $serializer,
        private readonly EntityManagerInterface $em,
        private readonly AccountRepository $accounts,
    ) {
        parent::__construct($serializer);
    }

    #[Route('', name: 'api_accounts_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $accounts = $this->accounts->findByOwner($this->currentUser());
        $data = array_map(fn (Account $a) => $this->withBalance($a), $accounts);

        return new JsonResponse($data);
    }

    #[Route('/{id}', name: 'api_accounts_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $account = $this->ownedAccount($id);

        return new JsonResponse($this->withBalance($account));
    }

    #[Route('', name: 'api_accounts_create', methods: ['POST'])]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: AccountRequest::class)))]
    public function create(#[MapRequestPayload] AccountRequest $dto): JsonResponse
    {
        $account = new Account();
        $account->setOwner($this->currentUser());
        $this->apply($account, $dto);

        $this->em->persist($account);
        $this->em->flush();

        return new JsonResponse($this->withBalance($account), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_accounts_update', methods: ['PUT'])]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: AccountRequest::class)))]
    public function update(string $id, #[MapRequestPayload] AccountRequest $dto): JsonResponse
    {
        $account = $this->ownedAccount($id);
        $this->apply($account, $dto);
        $this->em->flush();

        return new JsonResponse($this->withBalance($account));
    }

    #[Route('/{id}', name: 'api_accounts_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $account = $this->ownedAccount($id);
        $this->em->remove($account);
        $this->em->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function apply(Account $account, AccountRequest $dto): void
    {
        $account->setName($dto->name);
        $account->setCurrency(strtoupper($dto->currency));
        $account->setOpeningBalance($dto->openingBalance);
    }

    private function ownedAccount(string $id): Account
    {
        $account = $this->accounts->find($id);
        if ($account === null || $account->getOwner() !== $this->currentUser()) {
            throw $this->createNotFoundException('Account not found.');
        }

        return $account;
    }

    /**
     * @return array<string, mixed>
     */
    private function withBalance(Account $account): array
    {
        return [
            'id' => (string) $account->getId(),
            'name' => $account->getName(),
            'currency' => $account->getCurrency(),
            'openingBalance' => (float) $account->getOpeningBalance(),
            'currentBalance' => $this->accounts->currentBalance($account),
            'archived' => $account->isArchived(),
        ];
    }
}
