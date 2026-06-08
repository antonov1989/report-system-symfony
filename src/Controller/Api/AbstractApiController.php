<?php

namespace App\Controller\Api;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;

abstract class AbstractApiController extends AbstractController
{
    public function __construct(protected readonly SerializerInterface $serializer)
    {
    }

    protected function currentUser(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }

    /**
     * Serialize data with the given groups into a JSON response.
     *
     * @param string[] $groups
     */
    protected function serialized(mixed $data, array $groups, int $status = JsonResponse::HTTP_OK): JsonResponse
    {
        $json = $this->serializer->serialize($data, 'json', ['groups' => $groups]);

        return new JsonResponse($json, $status, [], true);
    }
}
