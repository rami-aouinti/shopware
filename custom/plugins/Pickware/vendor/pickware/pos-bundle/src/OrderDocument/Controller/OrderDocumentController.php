<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\OrderDocument\Controller;

use Pickware\ApiVersioningBundle\Attributes\ApiLayer;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityManagerException;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\ResponseFactory;
use Pickware\PickwarePos\OrderDocument\ApiVersioning\ApiVersion20240118\OrderDocumentCreationApiLayer as ApiVersion20240118OrderDocumentCreationApiLayer;
use Pickware\PickwarePos\OrderDocument\ApiVersioning\ApiVersion20240118\OrderDocumentUploadApiLayer as ApiVersion20240118OrderDocumentUploadApiLayer;
use Pickware\PickwarePos\OrderDocument\Controller\PayloadValidation\DocumentConfig;
use Pickware\PickwarePos\OrderDocument\Controller\PayloadValidation\UploadDocumentConfig;
use Pickware\PickwarePos\OrderDocument\OrderDocumentMailerService;
use Pickware\ShopwareExtensionsBundle\OrderDocument\OrderDocumentService;
use Pickware\ValidationBundle\Annotation\AclProtected;
use Pickware\ValidationBundle\Annotation\JsonParameter;
use Pickware\ValidationBundle\Annotation\JsonParameterAsUuid;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Constraints\NotBlank;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class OrderDocumentController
{
    private OrderDocumentService $orderDocumentService;
    private EntityManager $entityManager;
    private DocumentGenerator $documentGenerator;
    private OrderDocumentMailerService $orderDocumentMailerService;
    private Serializer $serializer;

    public function __construct(
        OrderDocumentService $orderDocumentService,
        EntityManager $entityManager,
        DocumentGenerator $documentGenerator,
        OrderDocumentMailerService $orderDocumentMailerService,
    ) {
        $this->orderDocumentService = $orderDocumentService;
        $this->entityManager = $entityManager;
        $this->documentGenerator = $documentGenerator;
        $this->orderDocumentMailerService = $orderDocumentMailerService;
        $this->serializer = new Serializer([new ObjectNormalizer(), new ArrayDenormalizer()], [new JsonEncoder()]);
    }

    #[ApiLayer(ids: [ApiVersion20240118OrderDocumentCreationApiLayer::class])]
    #[AclProtected(privilege: 'pickware_pos')]
    #[Route(
        path: '/api/_action/pickware-pos/create-document',
        name: 'api.action.pickware-pos.create-document',
        methods: ['POST'],
    )]
    public function createDocument(
        #[JsonParameterAsUuid] string $documentId,
        #[JsonParameterAsUuid] string $orderId,
        #[JsonParameterAsUuid] string $documentTypeId,
        #[JsonParameter] DocumentConfig $documentConfig,
        #[JsonParameter] ?string $documentNumber,
        Context $context,
    ): JsonResponse {
        // Check for existing document having the given ID to make this action idempotent
        $existingDocument = $this->entityManager->findByPrimaryKey(
            DocumentDefinition::class,
            $documentId,
            $context,
        );
        if ($existingDocument) {
            return $this->makeDocumentCreatedResponse($documentId, $context);
        }

        try {
            $context->scope(
                Context::SYSTEM_SCOPE,
                fn() => $this->orderDocumentService->createDocument(
                    $orderId,
                    $documentTypeId,
                    $context,
                    [
                        'documentConfig' => $documentConfig->toArray(),
                        'documentNumber' => $documentNumber,
                    ],
                    $documentId,
                ),
            );
        } catch (EntityManagerException $e) {
            $jsonApiError = $e->serializeToJsonApiError();
            switch ($jsonApiError->getCode()) {
                case EntityManagerException::ERROR_CODE_ENTITY_WITH_PRIMARY_KEY_NOT_FOUND:
                    $httpStatus = (string) Response::HTTP_BAD_REQUEST;
                    break;
                default:
                    $httpStatus = (string) Response::HTTP_INTERNAL_SERVER_ERROR;
            }
            $jsonApiError->setStatus($httpStatus);

            return $jsonApiError->toJsonApiErrorResponse();
        }

        return $this->makeDocumentCreatedResponse($documentId, $context);
    }

    #[ApiLayer(ids: [ApiVersion20240118OrderDocumentUploadApiLayer::class])]
    #[AclProtected('pickware_pos')]
    #[Route(
        path: '/api/_action/pickware-pos/upload-document',
        name: 'api.action.pickware-pos.upload-document',
        methods: ['POST'],
    )]
    public function uploadDocument(
        #[JsonParameterAsUuid] string $documentId,
        #[JsonParameterAsUuid] string $orderId,
        #[JsonParameterAsUuid] string $documentTypeId,
        #[JsonParameter] ?string $documentNumber,
        Request $request,
        Context $context,
    ): JsonResponse {
        // Check for existing document having the given ID to make this action idempotent
        $existingDocument = $this->entityManager->findByPrimaryKey(
            DocumentDefinition::class,
            $documentId,
            $context,
        );
        if ($existingDocument) {
            return $this->makeDocumentCreatedResponse($documentId, $context);
        }

        /** @var UploadedFile|null $documentFile */
        $documentFile = $request->files->get('documentFile');
        if ($documentFile === null) {
            return ResponseFactory::createParameterMissingResponse('documentFile');
        }

        $data = (string)$request->request->all()['documentConfig'];

        try {
            $documentConfig = $this->serializer
                ->deserialize($data, UploadDocumentConfig::class, 'json');
        } catch (UnexpectedValueException $exception) {
            return (new JsonApiError([
                'status' => Response::HTTP_BAD_REQUEST,
                'title' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
                'detail' => sprintf('Parameter "documentConfig" is invalid: "%s"', $exception->getMessage()),
            ]))->toJsonApiErrorResponse();
        }

        try {
            $context->scope(
                Context::SYSTEM_SCOPE,
                fn() => $this->orderDocumentService->uploadDocument(
                    $orderId,
                    $documentTypeId,
                    $context,
                    [
                        'documentConfig' => $documentConfig->toArray(),
                        'documentNumber' => $documentNumber,
                        'documentFile' => [
                            'mimeType' => $documentFile->getClientMimeType(),
                            'extension' => $documentFile->getClientOriginalExtension(),
                            'content' => $documentFile->getContent(),
                        ],
                    ],
                    $documentId,
                ),
            );
        } catch (EntityManagerException $e) {
            $jsonApiError = $e->serializeToJsonApiError();
            switch ($jsonApiError->getCode()) {
                case EntityManagerException::ERROR_CODE_ENTITY_WITH_PRIMARY_KEY_NOT_FOUND:
                    $httpStatus = (string) Response::HTTP_BAD_REQUEST;
                    break;
                default:
                    $httpStatus = (string) Response::HTTP_INTERNAL_SERVER_ERROR;
            }
            $jsonApiError->setStatus($httpStatus);

            return $jsonApiError->toJsonApiErrorResponse();
        }

        return $this->makeDocumentCreatedResponse($documentId, $context);
    }

    #[AclProtected('pickware_pos')]
    #[Route(
        path: '/api/_action/pickware-pos/send-document',
        name: 'api.action.pickware-pos.send-document',
        methods: ['POST'],
    )]
    public function sendDocument(
        #[JsonParameterAsUuid] string $documentId,
        #[JsonParameter([new NotBlank()])] string $emailAddress,
        Context $context,
    ): Response {
        $document = $context->scope(
            Context::SYSTEM_SCOPE,
            fn() => $this->entityManager->findByPrimaryKey(
                DocumentDefinition::class,
                $documentId,
                $context,
            ),
        );

        if (!$document) {
            return (new JsonApiError([
                'status' => Response::HTTP_BAD_REQUEST,
                'title' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
                'detail' => sprintf('Document with ID "%s" not found.', $documentId),
            ]))->toJsonApiErrorResponse();
        }
        $context->scope(
            Context::SYSTEM_SCOPE,
            fn() => $this->orderDocumentMailerService->sendDocumentToEmailAddress(
                $documentId,
                $emailAddress,
                $context,
            ),
        );

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function makeDocumentCreatedResponse(string $documentId, Context $context): JsonResponse
    {
        /** @var DocumentEntity $document */
        $document = $context->scope(
            Context::SYSTEM_SCOPE,
            fn() => $this->entityManager->getByPrimaryKey(
                DocumentDefinition::class,
                $documentId,
                $context,
                [
                    'documentMediaFile',
                    'documentType',
                ],
            ),
        );
        $renderedDocument = $context->scope(
            Context::SYSTEM_SCOPE,
            fn() => $this->documentGenerator->readDocument($documentId, $context),
        );

        return new JsonResponse(
            [
                'document' => $document->jsonSerialize(),
                'documentFileData' => base64_encode($renderedDocument->getContent()),
            ],
            Response::HTTP_CREATED,
        );
    }
}
