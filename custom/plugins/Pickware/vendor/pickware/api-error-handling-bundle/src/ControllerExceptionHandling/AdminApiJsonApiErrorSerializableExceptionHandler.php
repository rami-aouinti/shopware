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

use DateTimeInterface;
use Pickware\ApiErrorHandlingBundle\ServerOverloadException\ServerOverloadException;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;
use Pickware\HttpUtils\JsonApi\JsonApiErrorsSerializable;
use Pickware\PhpStandardLibrary\Json\Json;
use function Pickware\PhpStandardLibrary\Language\convertExceptionToArray;
use ReflectionClass;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Throwable;

class AdminApiJsonApiErrorSerializableExceptionHandler implements EventSubscriberInterface
{
    // Use Priority 0 because Shopware uses -1 in its  ResponseExceptionListener, and we want to run BEFORE
    // Shopware. Otherwise, Shopware would handle our error.
    public const PRIORITY = 0;

    public function __construct(
        private readonly bool $debug,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ExceptionEvent::class => [
                'onKernelException',
                self::PRIORITY,
            ],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        if (!($throwable instanceof JsonApiErrorSerializable) && !($throwable instanceof JsonApiErrorsSerializable)) {
            return;
        }

        $routeScopes = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE) ?? [];
        // Only the Admin API uses JSON API therefore we can only respond with JSON API error here.
        if (!in_array(ApiRouteScope::ID, $routeScopes)) {
            return;
        }

        if ($throwable instanceof JsonApiErrorSerializable) {
            $errors = new JsonApiErrors([$throwable->serializeToJsonApiError()]);
        } else {
            $errors = $throwable->serializeToJsonApiErrors();
        }
        $exceptionDetails = $this->getExceptionDetails($throwable);

        $httpStatusCode = $this->getHttpStatusCode($throwable) ?? Response::HTTP_INTERNAL_SERVER_ERROR;

        $response = $errors->toJsonApiErrorResponse($httpStatusCode);
        if ($throwable instanceof ServerOverloadException && $throwable->getRetryAfter()) {
            $response->headers->set(
                'Retry-After',
                $throwable->getRetryAfter()->format(DateTimeInterface::RFC7231),
            );
        }

        if ($this->debug) {
            $json = Json::decodeToObject($response->getContent());
            $json->_exceptionDetails = $exceptionDetails;
            $response->setData($json);
        }

        $event->setResponse($response);
    }

    private function getExceptionDetails(Throwable $exception): array
    {
        $details = convertExceptionToArray($exception);
        $previous = $exception->getPrevious();

        if ($previous) {
            $details['previous'] = self::getExceptionDetails($previous);

            if ($previous instanceof JsonApiErrorSerializable) {
                $details['previous']['jsonApiError'] = $previous->serializeToJsonApiError();
            }
            if ($previous instanceof JsonApiErrorsSerializable) {
                $details['previous']['jsonApiErrors'] = $previous->serializeToJsonApiErrors();
            }
        }

        return $details;
    }

    private function getHttpStatusCode(Throwable $exception): ?int
    {
        $reflectionClass = new ReflectionClass($exception);
        $attributes = $reflectionClass->getAttributes(WithHttpStatus::class);

        if (count($attributes) === 0) {
            return null;
        }

        /** @var WithHttpStatus $withHttpStatusAttribute */
        $withHttpStatusAttribute = $attributes[0]->newInstance();

        return $withHttpStatusAttribute->statusCode;
    }
}
