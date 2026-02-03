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

use BackedEnum;
use LogicException;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\ValidationBundle\Annotation\JsonParameter;
use ReflectionEnum;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use ValueError;

/**
 * A subscriber and resolver, that maps marked controller parameters to their specified values from incoming JSON requests
 * and validates them with the given type.
 */
class JsonRequestValueResolver implements ValueResolverInterface, EventSubscriberInterface
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
    ) {}

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        /** @var JsonParameter $attribute */
        $attribute = $argument->getAttributesOfType(
            JsonParameter::class,
            flags: ArgumentMetadata::IS_INSTANCEOF, // Also allow child-classes like MapUuidRequestParameter
        )[0] ?? null;

        if (!$attribute) {
            return [];
        }

        if ($argument->isVariadic()) {
            throw new LogicException(sprintf('Mapping variadic argument "$%s" is not supported.', $argument->getName()));
        }

        $attribute->setArgumentMetadata($argument);

        return [$attribute];
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER_ARGUMENTS => 'onKernelControllerArguments',
        ];
    }

    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        $mapRequestParameter = function(mixed $controllerArgument) use ($event) {
            if (!$controllerArgument instanceof JsonParameter) {
                return $controllerArgument;
            }

            $argumentMetadata = $controllerArgument->getArgumentMetadata();
            $type = $argumentMetadata->getType();

            if (!$type) {
                throw new HttpException(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    sprintf(
                        'Could not format the "$%s" controller argument: argument should be typed.',
                        $argumentMetadata->getName(),
                    ),
                );
            }

            $request = $event->getRequest();
            $requestParameters = $request->request->all();
            $argumentValue = $requestParameters[$argumentMetadata->getName()] ?? null;

            if ($argumentValue === null) {
                $argumentValue = match (true) {
                    $argumentMetadata->hasDefaultValue() => $argumentMetadata->getDefaultValue(),
                    $argumentMetadata->isNullable() => null,
                    default => throw new HttpException(
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                        sprintf(
                            'Could not format the "$%s" controller argument: argument should not be null.',
                            $argumentMetadata->getName(),
                        ),
                    ),
                };
            }

            if ($argumentValue === null && $argumentMetadata->isNullable()) {
                $payload = null;
            } elseif (is_subclass_of($type, BackedEnum::class)) {
                $enumType = (new ReflectionEnum($type))->getBackingType()?->getName();
                if ($enumType === 'string' && !is_string($argumentValue)) {
                    throw new HttpException(
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                        sprintf(
                            'Could not format the "$%s" controller argument: argument should be of type string.',
                            $argumentMetadata->getName(),
                        ),
                    );
                }
                if ($enumType === 'int' && !is_int($argumentValue)) {
                    throw new HttpException(
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                        sprintf(
                            'Could not format the "$%s" controller argument: argument should be of type int.',
                            $argumentMetadata->getName(),
                        ),
                    );
                }

                try {
                    $payload = $type::from($argumentValue);
                } catch (ValueError $e) {
                    throw new HttpException(
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                        sprintf(
                            'Could not format the "$%s" controller argument: invalid value for enum %s.',
                            $argumentMetadata->getName(),
                            $type,
                        ),
                        $e,
                    );
                }
            } elseif (!is_array($argumentValue)) {
                $typeValidationFunction = 'is_' . $type;
                if (!$typeValidationFunction($argumentValue)) {
                    throw new HttpException(
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                        sprintf(
                            'Could not format the "$%s" controller argument: argument should be of type %s.',
                            $argumentMetadata->getName(),
                            $type,
                        ),
                    );
                }
                $payload = $argumentValue;
            } elseif ($type === 'array') {
                $payload = $argumentValue;
            } elseif (method_exists($type, 'fromArray') && is_callable([$type, 'fromArray'])) {
                $payload = call_user_func(sprintf('%s::fromArray', $type), $argumentValue);
            } else {
                $payload = $this->serializer
                    ->deserialize(Json::stringify($argumentValue), $type, 'json');
            }

            $violations = $this->validator->validate($payload, $controllerArgument->validations);

            if (count($violations)) {
                throw new HttpException(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    implode(
                        "\n",
                        array_map(
                            fn($e) => $e->getMessage(),
                            iterator_to_array($violations),
                        ),
                    ),
                    new ValidationFailedException($payload, $violations),
                );
            }

            return $payload;
        };

        $controllerArguments = $event->getArguments();
        $newArguments = array_map($mapRequestParameter, $controllerArguments);

        $event->setArguments($newArguments);
    }
}
