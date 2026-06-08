<?php

namespace App\Controller\Api;

use App\Dto\BudgetRequest;
use App\Entity\Budget;
use App\Entity\Category;
use App\Repository\BudgetRepository;
use App\Repository\CategoryRepository;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/budgets')]
#[OA\Tag(name: 'Budgets')]
class BudgetController extends AbstractApiController
{
    public function __construct(
        SerializerInterface $serializer,
        private readonly EntityManagerInterface $em,
        private readonly BudgetRepository $budgets,
        private readonly CategoryRepository $categories,
        private readonly TransactionRepository $transactions,
    ) {
        parent::__construct($serializer);
    }

    /**
     * Budgets for a month (defaults to the current month), each with its spent amount and progress.
     */
    #[Route('', name: 'api_budgets_list', methods: ['GET'])]
    #[OA\Parameter(name: 'period', in: 'query', description: 'Month Y-m, e.g. 2026-06', schema: new OA\Schema(type: 'string'))]
    public function list(Request $request): JsonResponse
    {
        $period = $this->resolvePeriod($request->query->get('period'));
        $budgets = $this->budgets->findByOwnerAndPeriod($this->currentUser(), $period);

        $data = array_map(fn (Budget $b) => $this->present($b), $budgets);

        return new JsonResponse($data);
    }

    #[Route('', name: 'api_budgets_create', methods: ['POST'])]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: BudgetRequest::class)))]
    public function create(#[MapRequestPayload] BudgetRequest $dto): JsonResponse
    {
        $budget = new Budget();
        $budget->setOwner($this->currentUser());
        $budget->setCategory($this->ownedCategory($dto->categoryId));
        $budget->setLimitAmount($dto->limitAmount);
        $budget->setPeriod($this->parsePeriod($dto->period));

        $this->em->persist($budget);
        $this->em->flush();

        return new JsonResponse($this->present($budget), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_budgets_update', methods: ['PUT'])]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: BudgetRequest::class)))]
    public function update(string $id, #[MapRequestPayload] BudgetRequest $dto): JsonResponse
    {
        $budget = $this->ownedBudget($id);
        $budget->setCategory($this->ownedCategory($dto->categoryId));
        $budget->setLimitAmount($dto->limitAmount);
        $budget->setPeriod($this->parsePeriod($dto->period));
        $budget->setAlertSent(false); // re-arm the alert when the budget changes
        $this->em->flush();

        return new JsonResponse($this->present($budget));
    }

    #[Route('/{id}', name: 'api_budgets_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $budget = $this->ownedBudget($id);
        $this->em->remove($budget);
        $this->em->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Budget $budget): array
    {
        $limit = (float) $budget->getLimitAmount();
        $spent = $this->transactions->spentForCategoryInMonth($budget->getCategory(), $budget->getPeriod());

        return [
            'id' => (string) $budget->getId(),
            'category' => [
                'id' => (string) $budget->getCategory()->getId(),
                'name' => $budget->getCategory()->getName(),
                'color' => $budget->getCategory()->getColor(),
            ],
            'period' => $budget->getPeriod()->format('Y-m'),
            'limit' => $limit,
            'spent' => $spent,
            'remaining' => $limit - $spent,
            'progress' => $limit > 0 ? round($spent / $limit * 100, 1) : 0.0,
            'exceeded' => $spent > $limit,
        ];
    }

    private function resolvePeriod(?string $raw): \DateTimeImmutable
    {
        if (is_string($raw) && preg_match('/^\d{4}-\d{2}$/', $raw)) {
            return $this->parsePeriod($raw);
        }

        return new \DateTimeImmutable('first day of this month 00:00:00');
    }

    private function parsePeriod(string $period): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($period . '-01'))->setTime(0, 0);
    }

    private function ownedBudget(string $id): Budget
    {
        $budget = $this->budgets->find($id);
        if ($budget === null || $budget->getOwner() !== $this->currentUser()) {
            throw $this->createNotFoundException('Budget not found.');
        }

        return $budget;
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
