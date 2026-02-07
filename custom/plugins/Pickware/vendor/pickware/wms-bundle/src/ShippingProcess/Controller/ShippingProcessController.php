<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\ShippingProcess\Controller;

use Pickware\ApiVersioningBundle\Attributes\ApiLayer;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityResponseService;
use Pickware\PickwareWms\Device\Device;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessDefinition;
use Pickware\PickwareWms\ShippingProcess\ApiVersioning\ApiVersion20260122\TrackingCodeApiLayer as ApiVersion20260122TrackingCodeApiLayer;
use Pickware\PickwareWms\ShippingProcess\Model\ShippingProcessDefinition;
use Pickware\PickwareWms\ShippingProcess\Model\ShippingProcessEntity;
use Pickware\PickwareWms\ShippingProcess\Model\ShippingProcessStateMachine;
use Pickware\PickwareWms\ShippingProcess\ShippingProcessException;
use Pickware\PickwareWms\ShippingProcess\ShippingProcessReceiptContentGenerator;
use Pickware\PickwareWms\ShippingProcess\ShippingProcessReceiptDocumentGenerator;
use Pickware\PickwareWms\ShippingProcess\ShippingProcessService;
use Pickware\ShopwareExtensionsBundle\GeneratedDocument\GeneratedDocumentExtension as DocumentBundleResponseFactory;
use Pickware\ValidationBundle\Annotation\JsonParameter;
use Pickware\ValidationBundle\Annotation\JsonParameterAsUuid;
use Pickware\ValidationBundle\Annotation\JsonValidation;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class ShippingProcessController
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ShippingProcessService $shippingProcessService,
        private readonly EntityResponseService $entityResponseService,
        private readonly ShippingProcessReceiptContentGenerator $shippingProcessReceiptContentGenerator,
        private readonly ShippingProcessReceiptDocumentGenerator $shippingProcessReceiptDocumentGenerator,
    ) {}

    #[ApiLayer(ids: [
        ApiVersion20260122TrackingCodeApiLayer::class,
    ])]
    #[JsonValidation(schemaFilePath: 'payload-create-and-start-shipping-process.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/create-and-start-shipping-process', methods: ['PUT'])]
    public function createAndStartShippingProcess(#[JsonParameter] array $shippingProcess, Context $context): Response
    {
        try {
            $this->entityManager->runInTransactionWithRetry(function() use ($shippingProcess, $context): void {
                $pickingProcessIds = array_map(
                    fn(array $payload) => $payload['id'],
                    $shippingProcess['pickingProcesses'],
                );
                $this->entityManager->lockPessimistically(
                    PickingProcessDefinition::class,
                    ['id' => $pickingProcessIds],
                    $context,
                );

                $existingShippingProcess = $this->entityManager->findOneBy(
                    ShippingProcessDefinition::class,
                    ['id' => $shippingProcess['id']],
                    $context,
                );
                if (!$existingShippingProcess) {
                    $this->shippingProcessService->createShippingProcess($shippingProcess, $context);
                    $this->shippingProcessService->startOrContinue($shippingProcess['id'], $context);
                }
            });
        } catch (ShippingProcessException $exception) {
            return self::makeJsonApiErrorResponse($exception);
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            ShippingProcessDefinition::class,
            $shippingProcess['id'],
            $context,
        );
    }

    #[ApiLayer(ids: [
        ApiVersion20260122TrackingCodeApiLayer::class,
    ])]
    #[Route(path: '/api/_action/pickware-wms/continue-shipping-process', methods: ['PUT'])]
    public function continueShippingProcess(
        #[JsonParameterAsUuid] string $shippingProcessId,
        Context $context,
    ): Response {
        try {
            $this->entityManager->runInTransactionWithRetry(function() use ($shippingProcessId, $context): void {
                $this->entityManager->lockPessimistically(
                    ShippingProcessDefinition::class,
                    ['id' => $shippingProcessId],
                    $context,
                );
                /** @var ShippingProcessEntity $shippingProcess */
                $shippingProcess = $this->entityManager->getByPrimaryKey(
                    ShippingProcessDefinition::class,
                    $shippingProcessId,
                    $context,
                    [
                        'state',
                        'device',
                    ],
                );

                if ($shippingProcess->getState()->getTechnicalName() !== ShippingProcessStateMachine::STATE_IN_PROGRESS) {
                    $this->shippingProcessService->startOrContinue($shippingProcessId, $context);
                } elseif ($shippingProcess->getDeviceId() !== Device::getFromContext($context)->getId()) {
                    throw ShippingProcessException::invalidDevice(
                        $shippingProcess->getDeviceId(),
                        $shippingProcess->getDevice()?->getName(),
                        $shippingProcessId,
                    );
                }
            });
        } catch (ShippingProcessException $exception) {
            return self::makeJsonApiErrorResponse($exception);
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            ShippingProcessDefinition::class,
            $shippingProcessId,
            $context,
        );
    }

    #[ApiLayer(ids: [
        ApiVersion20260122TrackingCodeApiLayer::class,
    ])]
    #[Route(path: '/api/_action/pickware-wms/complete-shipping-process', methods: ['PUT'])]
    public function completeShippingProcess(
        #[JsonParameterAsUuid] string $shippingProcessId,
        Context $context,
    ): Response {
        try {
            $this->entityManager->runInTransactionWithRetry(function() use ($shippingProcessId, $context): void {
                $this->entityManager->lockPessimistically(
                    ShippingProcessDefinition::class,
                    ['id' => $shippingProcessId],
                    $context,
                );
                /** @var ShippingProcessEntity $shippingProcess */
                $shippingProcess = $this->entityManager->getByPrimaryKey(
                    ShippingProcessDefinition::class,
                    $shippingProcessId,
                    $context,
                    [
                        'state',
                        'device',
                    ],
                );

                if ($shippingProcess->getState()->getTechnicalName() !== ShippingProcessStateMachine::STATE_COMPLETED) {
                    $this->shippingProcessService->complete($shippingProcessId, $context);
                }
            });
        } catch (ShippingProcessException $exception) {
            return self::makeJsonApiErrorResponse($exception);
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            ShippingProcessDefinition::class,
            $shippingProcessId,
            $context,
        );
    }

    #[ApiLayer(ids: [
        ApiVersion20260122TrackingCodeApiLayer::class,
    ])]
    #[Route(path: '/api/_action/pickware-wms/take-over-shipping-process', methods: ['PUT'])]
    public function takeOverShippingProcess(
        #[JsonParameterAsUuid] string $shippingProcessId,
        Context $context,
    ): Response {
        try {
            $this->shippingProcessService->takeOver($shippingProcessId, $context);
        } catch (ShippingProcessException $exception) {
            return self::makeJsonApiErrorResponse($exception);
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            ShippingProcessDefinition::class,
            $shippingProcessId,
            $context,
        );
    }

    #[ApiLayer(ids: [
        ApiVersion20260122TrackingCodeApiLayer::class,
    ])]
    #[Route(path: '/api/_action/pickware-wms/defer-shipping-process', methods: ['PUT'])]
    public function deferShippingProcess(
        #[JsonParameterAsUuid] string $shippingProcessId,
        Context $context,
    ): Response {
        try {
            $this->entityManager->runInTransactionWithRetry(function() use ($shippingProcessId, $context): void {
                $this->entityManager->lockPessimistically(
                    ShippingProcessDefinition::class,
                    ['id' => $shippingProcessId],
                    $context,
                );
                /** @var ShippingProcessEntity $shippingProcess */
                $shippingProcess = $this->entityManager->getByPrimaryKey(
                    ShippingProcessDefinition::class,
                    $shippingProcessId,
                    $context,
                    [
                        'state',
                        'device',
                    ],
                );

                if ($shippingProcess->getState()->getTechnicalName() !== ShippingProcessStateMachine::STATE_DEFERRED) {
                    $this->shippingProcessService->defer($shippingProcessId, $context);
                }
            });
        } catch (ShippingProcessException $exception) {
            return self::makeJsonApiErrorResponse($exception);
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            ShippingProcessDefinition::class,
            $shippingProcessId,
            $context,
        );
    }

    #[ApiLayer(ids: [
        ApiVersion20260122TrackingCodeApiLayer::class,
    ])]
    #[Route(path: '/api/_action/pickware-wms/cancel-shipping-process', methods: ['PUT'])]
    public function cancelShippingProcess(
        #[JsonParameterAsUuid] string $shippingProcessId,
        Context $context,
    ): Response {
        try {
            $this->entityManager->runInTransactionWithRetry(function() use ($shippingProcessId, $context): void {
                $this->entityManager->lockPessimistically(
                    ShippingProcessDefinition::class,
                    ['id' => $shippingProcessId],
                    $context,
                );
                /** @var ShippingProcessEntity $shippingProcess */
                $shippingProcess = $this->entityManager->getByPrimaryKey(
                    ShippingProcessDefinition::class,
                    $shippingProcessId,
                    $context,
                    [
                        'state',
                        'device',
                    ],
                );

                if ($shippingProcess->getState()->getTechnicalName() !== ShippingProcessStateMachine::STATE_CANCELED) {
                    $this->shippingProcessService->cancel($shippingProcessId, $context);
                }
            });
        } catch (ShippingProcessException $exception) {
            return self::makeJsonApiErrorResponse($exception);
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            ShippingProcessDefinition::class,
            $shippingProcessId,
            $context,
        );
    }

    #[Route(path: '/api/_action/pickware-wms/shipping-process-receipt', methods: ['GET'])]
    public function getShippingProcessReceipt(
        #[MapQueryParameter]
        string $shippingProcessId,
        #[MapQueryParameter]
        ?string $languageId,
        Context $context,
    ): Response {
        /** @var ShippingProcessEntity $shippingProcess */
        $shippingProcess = $this->entityManager->findByPrimaryKey(
            ShippingProcessDefinition::class,
            $shippingProcessId,
            $context,
        );
        if (!$shippingProcess) {
            return ShippingProcessException::shippingProcessNotFound($shippingProcessId)
                ->serializeToJsonApiError()
                ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        $languageId ??= Defaults::LANGUAGE_SYSTEM;

        try {
            $templateVariables = $this->shippingProcessReceiptContentGenerator->generateForShippingProcess(
                $shippingProcessId,
                $languageId,
                $context,
            );
            $renderedDocument = $this->shippingProcessReceiptDocumentGenerator->generate($templateVariables, $languageId, $context);
        } catch (ShippingProcessException $exception) {
            return $exception
                ->serializeToJsonApiError()
                ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return DocumentBundleResponseFactory::createPdfResponse(
            $renderedDocument->getContent(),
            $renderedDocument->getName(),
            $renderedDocument->getContentType(),
        );
    }

    private static function makeJsonApiErrorResponse(
        ShippingProcessException $exception,
        int $statusCode = Response::HTTP_BAD_REQUEST,
    ): Response {
        return $exception->serializeToJsonApiError()->toJsonApiErrorResponse($statusCode);
    }
}
