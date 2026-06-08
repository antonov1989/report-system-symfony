<?php

namespace App\Controller\Api;

use App\Dto\RegisterRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api')]
class AuthController extends AbstractApiController
{
    public function __construct(
        SerializerInterface $serializer,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly UserRepository $users,
    ) {
        parent::__construct($serializer);
    }

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    #[OA\Tag(name: 'Auth')]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: RegisterRequest::class)))]
    #[OA\Response(response: 201, description: 'User created')]
    #[OA\Response(response: 409, description: 'Email already registered')]
    public function register(#[MapRequestPayload] RegisterRequest $dto): JsonResponse
    {
        if ($this->users->findOneBy(['email' => $dto->email]) !== null) {
            return $this->json(['error' => 'Email already registered.'], JsonResponse::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($dto->email);
        $user->setName($dto->name);
        $user->setDefaultCurrency(strtoupper($dto->defaultCurrency));
        $user->setPassword($this->hasher->hashPassword($user, $dto->password));

        $this->em->persist($user);
        $this->em->flush();

        return $this->serialized($user, ['user:read'], JsonResponse::HTTP_CREATED);
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    #[OA\Tag(name: 'Auth')]
    #[OA\Response(response: 200, description: 'Current authenticated user')]
    public function me(): JsonResponse
    {
        return $this->serialized($this->currentUser(), ['user:read']);
    }
}
