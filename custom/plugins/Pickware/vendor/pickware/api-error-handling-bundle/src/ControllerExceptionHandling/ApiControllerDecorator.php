<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ApiErrorHandlingBundle\ControllerExceptionHandling;

use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\Api\Controller\ApiController;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiControllerDecorator extends ApiController
{
    private ApiController $decoratedController;

    public function __construct(ApiController $decoratedController)
    {
        $this->decoratedController = $decoratedController;
    }

    public function setContainer(ContainerInterface $container): ?ContainerInterface
    {
        return $this->decoratedController->setContainer($container);
    }

    public function clone(Context $context, string $entity, string $id, Request $request): JsonResponse
    {
        try {
            return $this->decoratedController->clone($context, $entity, $id, $request);
        } catch (ApiControllerHandlable $exception) {
            return $exception->handleForApiController();
        }
    }

    public function createVersion(Request $request, Context $context, string $entity, string $id): Response
    {
        try {
            return $this->decoratedController->createVersion($request, $context, $entity, $id);
        } catch (ApiControllerHandlable $exception) {
            return $exception->handleForApiController();
        }
    }

    public function mergeVersion(Context $context, string $entity, string $versionId): JsonResponse
    {
        try {
            return $this->decoratedController->mergeVersion($context, $entity, $versionId);
        } catch (ApiControllerHandlable $exception) {
            return $exception->handleForApiController();
        }
    }

    public function deleteVersion(Context $context, string $entity, string $entityId, string $versionId): JsonResponse
    {
        try {
            return $this->decoratedController->deleteVersion($context, $entity, $entityId, $versionId);
        } catch (ApiControllerHandlable $exception) {
            return $exception->handleForApiController();
        }
    }

    public function detail(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        try {
            return $this->decoratedController->detail($request, $context, $responseFactory, $entityName, $path);
        } catch (ApiControllerHandlable $exception) {
            return $exception->handleForApiController();
        }
    }

    public function searchIds(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        try {
            return $this->decoratedController->searchIds($request, $context, $responseFactory, $entityName, $path);
        } catch (ApiControllerHandlable $exception) {
            return $exception->handleForApiController();
        }
    }

    public function search(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        try {
            return $this->decoratedController->search($request, $context, $responseFactory, $entityName, $path);
        } catch (ApiControllerHandlable $exception) {
            return $exception->handleForApiController();
        }
    }

    public function list(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        try {
            return $this->decoratedController->list($request, $context, $responseFactory, $entityName, $path);
        } catch (ApiControllerHandlable $exception) {
            return $exception->handleForApiController();
        }
    }

    public function create(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        try {
            return $this->decoratedController->create($request, $context, $responseFactory, $entityName, $path);
        } catch (ApiControllerHandlable $exception) {
            return $exception->handleForApiController();
        }
    }

    public function update(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        try {
            return $this->decoratedController->update($request, $context, $responseFactory, $entityName, $path);
        } catch (ApiControllerHandlable $exception) {
            return $exception->handleForApiController();
        }
    }

    public function delete(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        try {
            return $this->decoratedController->delete($request, $context, $responseFactory, $entityName, $path);
        } catch (ApiControllerHandlable $exception) {
            return $exception->handleForApiController();
        }
    }

    public function aggregate(
        Request $request,
        Context $context,
        ResponseFactoryInterface $responseFactory,
        string $entityName,
        string $path,
    ): Response {
        try {
            return $this->decoratedController->aggregate($request, $context, $responseFactory, $entityName, $path);
        } catch (ApiControllerHandlable $exception) {
            return $exception->handleForApiController();
        }
    }
}
