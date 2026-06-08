<?php

namespace App\Controller\Api;

use App\Dto\CategoryRequest;
use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/categories')]
#[OA\Tag(name: 'Categories')]
class CategoryController extends AbstractApiController
{
    public function __construct(
        SerializerInterface $serializer,
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categories,
    ) {
        parent::__construct($serializer);
    }

    #[Route('', name: 'api_categories_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->serialized(
            $this->categories->findByOwner($this->currentUser()),
            ['category:read'],
        );
    }

    #[Route('', name: 'api_categories_create', methods: ['POST'])]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: CategoryRequest::class)))]
    public function create(#[MapRequestPayload] CategoryRequest $dto): JsonResponse
    {
        $category = new Category();
        $category->setOwner($this->currentUser());
        $this->apply($category, $dto);

        $this->em->persist($category);
        $this->em->flush();

        return $this->serialized($category, ['category:read'], JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_categories_update', methods: ['PUT'])]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: CategoryRequest::class)))]
    public function update(string $id, #[MapRequestPayload] CategoryRequest $dto): JsonResponse
    {
        $category = $this->ownedCategory($id);
        $this->apply($category, $dto);
        $this->em->flush();

        return $this->serialized($category, ['category:read']);
    }

    #[Route('/{id}', name: 'api_categories_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $category = $this->ownedCategory($id);
        $this->em->remove($category);
        $this->em->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function apply(Category $category, CategoryRequest $dto): void
    {
        $category->setName($dto->name);
        $category->setType($dto->type);
        $category->setColor($dto->color);
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
