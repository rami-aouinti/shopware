<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ValidationBundle\Subscriber;

use Pickware\ValidationBundle\Annotation\JsonValidation;
use Pickware\ValidationBundle\JsonSchema;
use Pickware\ValidationBundle\JsonValidator;
use Pickware\ValidationBundle\JsonValidatorException;
use ReflectionClass;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * A subscriber that checks whether the executed controller method is annotated with @JsonValidation and executes
 * a JSON validation for the request body if so.
 */
class JsonValidationAnnotationSubscriber implements EventSubscriberInterface
{
    private JsonValidator $jsonValidator;

    public function __construct(JsonValidator $jsonValidator)
    {
        $this->jsonValidator = $jsonValidator;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                'onKernelController',
                // Use a low priority to ensure all other subscribers like authorization and context resolving did already run.
                -1000000,
            ],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!is_array($event->getController())) {
            return;
        }

        [$controllerObject, $method] = $event->getController();

        $reflectionClass = new ReflectionClass($controllerObject);
        $method = $reflectionClass->getMethod($method);
        $jsonValidationAttributes = $method->getAttributes(JsonValidation::class);

        if (empty($jsonValidationAttributes)) {
            return;
        }

        $request = $event->getRequest();
        foreach ($jsonValidationAttributes as $jsonValidationAttribute) {
            $jsonValidationSchemaFilePath = dirname($reflectionClass->getFileName())
                . '/'
                . $jsonValidationAttribute->newInstance()->schemaFilePath;
            try {
                $jsonSchema = JsonSchema::createFromFile($jsonValidationSchemaFilePath);
                $this->jsonValidator->validateJsonStringAgainstJsonSchema($request->getContent(), $jsonSchema);
            } catch (JsonValidatorException $exception) {
                $response = $exception->serializeToJsonApiError()->setStatus(Response::HTTP_BAD_REQUEST)->toJsonApiErrorResponse();
                $event->setController(fn() => $response);
            }
        }
    }
}
