<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Shipment;

use InvalidArgumentException;
use JetBrains\PhpStorm\Deprecated;
use JsonSerializable;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class ShipmentsOperationResult implements JsonSerializable
{
    private bool $successful;
    private string $description;

    /**
     * @var JsonApiError[]|null
     */
    private ?array $errors;

    /**
     * @var string[]
     */
    private array $processedShipmentIds;

    private function __construct() {}

    public function jsonSerialize(): array
    {
        return [
            'successful' => $this->successful,
            'description' => $this->description,
            // This property is deprecated and exists only for backwards compatibility as long as the method
            // getErrorMessages exists.
            'errorMessages' => $this->getErrorMessages(),
            'errors' => $this->errors,
            'processedShipmentIds' => $this->processedShipmentIds,
        ];
    }

    /**
     * @param string[] $processesShipmentIds
     */
    public static function createSuccessfulOperationResult(
        array $processesShipmentIds,
        string $description,
    ): self {
        $self = new self();
        $self->successful = true;
        $self->processedShipmentIds = $processesShipmentIds;
        $self->errors = null;
        $self->description = $description;

        return $self;
    }

    /**
     * Use this when you want to mark a shipment as "processed" without actually processing it.
     *
     * Example: The request is to cancel the shipment but the shipment is cancelled already.
     *
     * @param string[] $processesShipmentIds
     */
    public static function createNoOperationResult(array $processesShipmentIds): self
    {
        return self::createSuccessfulOperationResult($processesShipmentIds, 'No operation');
    }

    /**
     * @param string[] $processesShipmentIds
     * @param JsonApiError[] $errors
     */
    public static function createFailedOperationResult(
        array $processesShipmentIds,
        string $description,
        ?array $errors = null,
        #[Deprecated(reason: '$errorMessages will be removed in 3.0.0. Use $errors instead.')]
        ?array $errorMessages = null,
    ): self {
        $self = new self();
        $self->successful = false;
        $self->processedShipmentIds = $processesShipmentIds;
        $self->description = $description;
        if ($errorMessages !== null && $errors !== null) {
            throw new InvalidArgumentException('You must not provide both $errors and $errorMessages.');
        }
        if ($errorMessages !== null) {
            // Legacy call of type:
            // createFailedOperationResult(
            //     processesShipmentIds: [],
            //     description: '',
            //     errorMessages: [],
            // );

            trigger_error(
                'The $errorMessages parameter is deprecated. Use $errors instead.',
                E_USER_DEPRECATED,
            );
            $errors = array_map(
                fn(string $errorMessage) => new JsonApiError(['detail' => $errorMessage]),
                $errorMessages,
            );
        }
        if (is_string($errors[0] ?? null)) {
            // Legacy call of type:
            // createFailedOperationResult(
            //     [],
            //     '',
            //     [],
            // );
            trigger_error(
                'Passing error messages as strings is deprecated. Pass an array of JsonApiError objects instead.',
                E_USER_DEPRECATED,
            );
            $errors = array_map(
                fn(string $errorMessage) => new JsonApiError(['detail' => $errorMessage]),
                $errors,
            );
        }
        if ($errors === null) {
            throw new InvalidArgumentException('You must provide $errors.');
        }
        $self->errors = $errors;

        return $self;
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return string[]|null
     * @deprecated tag:next-major Will be removed. Use getErrors instead
     */
    public function getErrorMessages(): ?array
    {
        if ($this->errors === null) {
            return null;
        }

        return array_map(fn(JsonApiError $error) => $error->getDetail(), $this->errors);
    }

    public function getErrors(): ?array
    {
        return $this->errors;
    }

    /**
     * @return string[]
     */
    public function getProcessedShipmentIds(): array
    {
        return $this->processedShipmentIds;
    }
}
