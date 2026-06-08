<?php

namespace App\Controller\Api;

use App\Message\ImportTransactionsCsv;
use App\Repository\AccountRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;

#[Route('/api/import')]
#[OA\Tag(name: 'Import')]
class ImportController extends AbstractApiController
{
    public function __construct(
        SerializerInterface $serializer,
        private readonly AccountRepository $accounts,
        private readonly MessageBusInterface $bus,
        private readonly string $importDir,
    ) {
        parent::__construct($serializer);
    }

    #[Route('/transactions', name: 'api_import_transactions', methods: ['POST'])]
    #[OA\RequestBody(
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(properties: [
                new OA\Property(property: 'accountId', type: 'string'),
                new OA\Property(property: 'file', type: 'string', format: 'binary'),
            ]),
        ),
    )]
    #[OA\Response(response: 202, description: 'Import accepted and queued')]
    public function transactions(Request $request): JsonResponse
    {
        $accountId = (string) $request->request->get('accountId', '');
        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');

        if (!Uuid::isValid($accountId)) {
            return $this->json(['error' => 'A valid accountId is required.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($file === null) {
            return $this->json(['error' => 'A CSV file is required (field "file").'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $account = $this->accounts->find($accountId);
        if ($account === null || $account->getOwner() !== $this->currentUser()) {
            throw $this->createNotFoundException('Account not found.');
        }

        if (!is_dir($this->importDir)) {
            mkdir($this->importDir, 0775, true);
        }
        $stored = sprintf('%s/%s.csv', $this->importDir, Uuid::v7());
        $file->move($this->importDir, basename($stored));

        $this->bus->dispatch(new ImportTransactionsCsv(
            (string) $this->currentUser()->getId(),
            $accountId,
            $stored,
        ));

        return $this->json(['status' => 'accepted'], JsonResponse::HTTP_ACCEPTED);
    }
}
