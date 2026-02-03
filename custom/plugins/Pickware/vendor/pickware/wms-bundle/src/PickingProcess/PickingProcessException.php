<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProcess;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\HttpUtils\JsonApi\JsonApiErrorsSerializable;
use Pickware\PickwareErpStarter\Picking\PickingStrategyStockShortageException;
use Pickware\PickwareErpStarter\StockApi\StockMovementServiceValidationException;
use Pickware\ShippingBundle\Config\ConfigException;
use Throwable;

class PickingProcessException extends Exception implements JsonApiErrorsSerializable
{
    public const ERROR_CODE_NAMESPACE = 'PICKWARE_WMS__PICKING_PROCESS__';
    public const STOCK_IS_RESERVED = self::ERROR_CODE_NAMESPACE . 'STOCK_IS_RESERVED';
    public const STOCK_MOVEMENT_NOT_POSSIBLE = self::ERROR_CODE_NAMESPACE . 'STOCK_MOVEMENT_NOT_POSSIBLE';
    public const NO_STOCK_CONTAINER_ASSIGNED_FOR_DELIVERY = self::ERROR_CODE_NAMESPACE . 'NO_STOCK_CONTAINER_ASSIGNED_FOR_DELIVERY';
    public const NO_STOCK_CONTAINER_ASSIGNED_FOR_PICKING_PROCESS = self::ERROR_CODE_NAMESPACE . 'NO_STOCK_CONTAINER_ASSIGNED_FOR_PICKING_PROCESS';
    public const DELIVERY_DOES_NOT_BELONG_TO_PICKING_PROCESS = self::ERROR_CODE_NAMESPACE . 'DELIVERY_DOES_NOT_BELONG_TO_PICKING_PROCESS';
    public const PICKING_PROCESS_CANNOT_BE_EMPTY = self::ERROR_CODE_NAMESPACE . 'PICKING_PROCESS_CANNOT_BE_EMPTY';
    public const CREATION_OF_INVOICE_FAILED = self::ERROR_CODE_NAMESPACE . 'CREATION_OF_INVOICE_FAILED';
    public const CREATION_OF_DELIVERY_NOTE_FAILED = self::ERROR_CODE_NAMESPACE . 'CREATION_OF_DELIVERY_NOTE_FAILED';
    public const ORDER_WAS_CANCELLED = self::ERROR_CODE_NAMESPACE . 'ORDER_WAS_CANCELLED';
    public const INVALID_PICKING_PROCESS_STATE_FOR_ACTION = self::ERROR_CODE_NAMESPACE . 'INVALID_PICKING_PROCESS_STATE_FOR_ACTION';
    public const INVALID_DELIVERY_STATE_FOR_ACTION = self::ERROR_CODE_NAMESPACE . 'INVALID_DELIVERY_STATE_FOR_ACTION';
    public const DELIVERY_ALREADY_HAS_STOCK_CONTAINER = self::ERROR_CODE_NAMESPACE . 'DELIVERY_ALREADY_HAS_STOCK_CONTAINER';
    public const NO_ORDER_DELIVERIES = self::ERROR_CODE_NAMESPACE . 'NO_ORDER_DELIVERIES';
    public const NO_SHIPPING_CARRIER_INSTALLED = self::ERROR_CODE_NAMESPACE . 'NO_SHIPPING_CARRIER_INSTALLED';
    public const SHIPMENT_CREATION_FAILED = self::ERROR_CODE_NAMESPACE . 'SHIPMENT_CREATION_FAILED';
    public const PICKING_PROCESS_NOT_FOUND = self::ERROR_CODE_NAMESPACE . 'PICKING_PROCESS_NOT_FOUND';
    public const DELIVERY_NOT_FOUND = self::ERROR_CODE_NAMESPACE . 'DELIVERY_NOT_FOUND';
    public const DELIVERIES_NOT_FOUND = self::ERROR_CODE_NAMESPACE . 'DELIVERIES_NOT_FOUND';
    public const DELIVERIES_IN_INVALID_STATE_FOR_DOCUMENT_CREATION_SKIPPING = self::ERROR_CODE_NAMESPACE . 'DELIVERIES_IN_INVALID_STATE_FOR_DOCUMENT_CREATION_SKIPPING';
    public const NO_ORDER_FOUND = self::ERROR_CODE_NAMESPACE . 'NO_ORDER_FOUND';
    public const STOCK_CONTAINER_ALREADY_IN_USE = self::ERROR_CODE_NAMESPACE . 'STOCK_CONTAINER_ALREADY_IN_USE';

    private JsonApiErrors $jsonApiErrors;

    public function __construct(JsonApiErrors $jsonApiErrors, ?Throwable $previous = null)
    {
        $this->jsonApiErrors = $jsonApiErrors;
        parent::__construct($jsonApiErrors->getThrowableMessage(), 0, $previous);
    }

    public static function pickingProfileNotFound(): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'title' => [
                        'en' => 'Picking profile not found',
                        'de' => 'Pickprofil nicht gefunden',
                    ],
                    'detail' => [
                        'en' => 'The picking profile does not exists. Maybe it has been deleted.',
                        'de' => 'Das Pickprofil existiert nicht. Möglicherweise wurde es gelöscht.',
                    ],
                ]),
            ]),
        );
    }

    public static function partialDeliveryNotAllowed(PickingStrategyStockShortageException $exception): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'title' => [
                        'en' => 'Partial delivery not allowed',
                        'de' => 'Teillieferung nicht erlaubt',
                    ],
                    'detail' => [
                        'en' => sprintf(
                            'Partial deliveries are not allowed and the following products do not have enough stock: %s.',
                            implode(', ', $exception->getProductNumbers()),
                        ),
                        'de' => sprintf(
                            'Teillieferungen sind nicht erlaubt und folgende Produkte haben nicht genug Bestand: %s.',
                            implode(', ', $exception->getProductNumbers()),
                        ),
                    ],
                    'meta' => [
                        'productNumbers' => $exception->getProductNumbers(),
                    ],
                ]),
            ]),
            $exception,
        );
    }

    public function serializeToJsonApiErrors(): JsonApiErrors
    {
        return $this->jsonApiErrors;
    }

    public static function stockIsReservedForOtherPickingProcess(): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::STOCK_IS_RESERVED,
                    'title' => [
                        'en' => 'Stock is reserved',
                        'de' => 'Bestand ist reserviert',
                    ],
                    'detail' => [
                        'en' => 'The stock is already reserved for another picking process.',
                        'de' => 'Der Bestand ist bereits für einen anderen Kommissioniervorgang reserviert.',
                    ],
                ]),
            ]),
        );
    }

    public static function stockMovementNotPossible(StockMovementServiceValidationException $previous): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::STOCK_MOVEMENT_NOT_POSSIBLE,
                    'title' => [
                        'en' => 'Stock cannot be moved',
                        'de' => 'Bestand kann nicht bewegt werden',
                    ],
                    'detail' => [
                        'en' => sprintf(
                            'The stock cannot be moved because of the following error: %s',
                            $previous->getMessage(),
                        ),
                        'de' => sprintf(
                            'Der Bestand kann nicht bewegt werden, da ein Fehler aufgetreten ist: %s',
                            $previous->getMessage(),
                        ),
                    ],
                    'meta' => ['reason' => $previous->serializeToJsonApiError()],
                ]),
            ]),
            previous: $previous,
        );
    }

    public static function noStockContainerAssignedForDelivery(string $deliveryId): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::NO_STOCK_CONTAINER_ASSIGNED_FOR_DELIVERY,
                    'title' => [
                        'en' => 'No stock container assigned for fulfillment order',
                        'de' => 'Auftrag keiner Kiste zugeordnet',
                    ],
                    'detail' => [
                        'en' => sprintf(
                            'The fulfillment order with ID "%s" has no stock container assigned.',
                            $deliveryId,
                        ),
                        'de' => sprintf(
                            'Dem Auftrag mit ID "%s" ist keine Kiste zugeordnet.',
                            $deliveryId,
                        ),
                    ],
                    'meta' => ['deliveryId' => $deliveryId],
                ]),
            ]),
        );
    }

    public static function noStockContainerAssignedForPickingProcess(string $pickingProcessId): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::NO_STOCK_CONTAINER_ASSIGNED_FOR_PICKING_PROCESS,
                    'title' => [
                        'en' => 'No stock container assigned for picking process',
                        'de' => 'Kommissioniervorgang keiner Kiste zugeordnet',
                    ],
                    'detail' => [
                        'en' => sprintf(
                            'The picking process with ID "%s" has no stock container assigned.',
                            $pickingProcessId,
                        ),
                        'de' => sprintf(
                            'Dem Kommissioniervorgang mit ID "%s" ist keine Kiste zugeordnet.',
                            $pickingProcessId,
                        ),
                    ],
                    'meta' => ['pickingProcessId' => $pickingProcessId],
                ]),
            ]),
        );
    }

    public static function deliveriesDoNotBelongToPickingProcess(
        array $foreignDeliveryIds,
        string $pickingProcessId,
    ): self {
        $errors = array_map(
            fn(string $foreignDeliveryId) => new LocalizableJsonApiError([
                'code' => self::DELIVERY_DOES_NOT_BELONG_TO_PICKING_PROCESS,
                'title' => [
                    'en' => 'Fulfillment order does not belong to picking process',
                    'de' => 'Auftrag ist nicht Teil des Kommissioniervorgangs',
                ],
                'detail' => [
                    'en' => sprintf(
                        'The fulfillment order with ID "%1$s" does not belong to the picking process with ID "%2$s".',
                        $foreignDeliveryId,
                        $pickingProcessId,
                    ),
                    'de' => sprintf(
                        'Der Auftrag mit ID "%1$s" ist nicht Teil des Kommissioniervorgangs mit ID "%2$s".',
                        $foreignDeliveryId,
                        $pickingProcessId,
                    ),
                ],
                'meta' => [
                    'pickingProcessId' => $pickingProcessId,
                    'deliveryId' => $foreignDeliveryId,
                ],
            ]),
            $foreignDeliveryIds,
        );

        return new self(new JsonApiErrors($errors));
    }

    public static function pickingProcessCannotBeEmpty(): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::PICKING_PROCESS_CANNOT_BE_EMPTY,
                    'title' => [
                        'en' => 'Picking process cannot be empty',
                        'de' => 'Kommissioniervorgang darf nicht leer sein',
                    ],
                    'detail' => [
                        'en' => 'Cannot defer the last fulfillment order of a batch picking process.',
                        'de' => 'Der letzte Auftrag eines Kommissioniervorgangs kann nicht zurückgestellt werden.',
                    ],
                ]),
            ]),
        );
    }

    public static function creationOfInvoiceFailed(string $orderId, string $orderNumber, Exception $previous): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::CREATION_OF_INVOICE_FAILED,
                    'title' => [
                        'en' => 'Creation of invoice failed',
                        'de' => 'Erstellen der Rechnung fehlgeschlagen',
                    ],
                    'detail' => [
                        'en' => sprintf(
                            'The creation of the invoice for order "%1$s" failed because an error occurred: %2$s',
                            $orderNumber,
                            $previous->getMessage(),
                        ),
                        'de' => sprintf(
                            'Das Erstellen der Rechnung für Bestellung "%1$s" ist fehlgeschlagen, da ein Fehler '
                            . 'aufgetreten ist: %2$s',
                            $orderNumber,
                            $previous->getMessage(),
                        ),
                    ],
                    'meta' => [
                        'orderId' => $orderId,
                        'orderNumber' => $orderNumber,
                        'reasons' => JsonApiErrors::fromThrowable($previous),
                    ],
                ]),
            ]),
            previous: $previous,
        );
    }

    public static function creationOfDeliveryNoteFailed(string $orderId, string $orderNumber, Exception $previous): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::CREATION_OF_DELIVERY_NOTE_FAILED,
                    'title' => [
                        'en' => 'Creation of delivery note failed',
                        'de' => 'Erstellen des Lieferscheins fehlgeschlagen',
                    ],
                    'detail' => [
                        'en' => sprintf(
                            'The creation of the delivery note for order "%1$s" failed because an error occurred: %2$s',
                            $orderNumber,
                            $previous->getMessage(),
                        ),
                        'de' => sprintf(
                            'Das Erstellen des Lieferscheins für Bestellung "%1$s" ist fehlgeschlagen, da ein Fehler '
                            . 'aufgetreten ist: %2$s',
                            $orderNumber,
                            $previous->getMessage(),
                        ),
                    ],
                    'meta' => [
                        'orderId' => $orderId,
                        'orderNumber' => $orderNumber,
                        'reasons' => JsonApiErrors::fromThrowable($previous),
                    ],
                ]),
            ]),
            previous: $previous,
        );
    }

    public static function orderWasCancelled(string $orderNumber, string $orderId): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::ORDER_WAS_CANCELLED,
                    'title' => [
                        'en' => 'Order was cancelled',
                        'de' => 'Bestellung storniert',
                    ],
                    'detail' => [
                        'en' => sprintf(
                            'The order "%s" for this fulfillment order was cancelled in the meantime.',
                            $orderNumber,
                        ),
                        'de' => sprintf(
                            'Die Bestellung "%s" dieses Auftrags wurde mittlerweile storniert.',
                            $orderNumber,
                        ),
                    ],
                    'meta' => [
                        'orderId' => $orderId,
                        'orderNumber' => $orderNumber,
                    ],
                ]),
            ]),
        );
    }

    /**
     * @param string[] $expectedStateNames
     */
    public static function invalidPickingProcessStateForAction(
        string $pickingProcessId,
        string $actualStateName,
        array $expectedStateNames,
    ): self {
        $joinedExpectedStateNames = implode(
            ', ',
            array_map(fn(string $stateName) => sprintf('"%s"', $stateName), $expectedStateNames),
        );

        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::INVALID_PICKING_PROCESS_STATE_FOR_ACTION,
                    'title' => [
                        'en' => 'Invalid picking process state',
                        'de' => 'Kommissioniervorgang in ungültigem Status',
                    ],
                    'detail' => [
                        'en' => sprintf(
                            'The picking process is not in one of the expected states %1$s but in the state "%2$s". '
                            . 'The picking process may have been processed on another device in the meantime.',
                            $joinedExpectedStateNames,
                            $actualStateName,
                        ),
                        'de' => sprintf(
                            'Der Kommissioniervorgang befindet sich nicht in einem der erwarteten Status %1$s sondern'
                            . ' im Status "%2$s". Eventuell wurde der Kommissioniervorgang in der Zwischenzeit auf '
                            . 'einem anderen Gerät bearbeitet.',
                            $joinedExpectedStateNames,
                            $actualStateName,
                        ),
                    ],
                    'meta' => [
                        'pickingProcessId' => $pickingProcessId,
                        'actualStateName' => $actualStateName,
                        'expectedStateNames' => $expectedStateNames,
                    ],
                ]),
            ]),
        );
    }

    public static function invalidDeliveryStateForAction(
        string $deliveryId,
        string $actualStateName,
        array $expectedStateNames,
    ): self {
        $joinedExpectedStateNames = implode(
            ', ',
            array_map(fn(string $stateName) => sprintf('"%s"', $stateName), $expectedStateNames),
        );

        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::INVALID_DELIVERY_STATE_FOR_ACTION,
                    'title' => [
                        'en' => 'Invalid fulfillment order state',
                        'de' => 'Auftrag in ungültigem Status',
                    ],
                    'detail' => [
                        'en' => sprintf(
                            'The fulfillment order is not in one of the expected states %1$s but in the state "%2$s". The '
                            . 'delivery may have been processed on another device in the meantime.',
                            $joinedExpectedStateNames,
                            $actualStateName,
                        ),
                        'de' => sprintf(
                            'Der Auftrag befindet sich nicht in einem der erwarteten Status %1$s sondern im Status '
                            . '"%2$s". Eventuell wurde der Auftrag in der Zwischenzeit auf einem anderen Gerät '
                            . 'bearbeitet.',
                            $joinedExpectedStateNames,
                            $actualStateName,
                        ),
                    ],
                    'meta' => [
                        'deliveryId' => $deliveryId,
                        'actualStateName' => $actualStateName,
                        'expectedStateNames' => $expectedStateNames,
                    ],
                ]),
            ]),
        );
    }

    public static function deliveryAlreadyHasStockContainer(
        string $deliveryId,
        string $stockContainerId,
        ?string $stockContainerNumber,
    ): self {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::DELIVERY_ALREADY_HAS_STOCK_CONTAINER,
                    'title' => [
                        'en' => 'Fulfillment order already has a stock container',
                        'de' => 'Auftrag wurde bereits eine Kiste zugewiesen',
                    ],
                    'detail' => [
                        'en' => sprintf(
                            'Cannot assign a stock container to the fulfillment order with ID "%1$s" because it already has '
                            . 'stock container with number "%2$s" assigned.',
                            $deliveryId,
                            $stockContainerNumber ?? '<unknown>',
                        ),
                        'de' => sprintf(
                            'Dem Auftrag mit ID "%1$s" kann keine Kiste zugewiesen werden, da ihr bereits die Kiste '
                            . 'mit der Nummer "%s" zugewiesen ist.',
                            $deliveryId,
                            $stockContainerNumber ?? '<unbekannt>',
                        ),
                    ],
                    'meta' => [
                        'deliveryId' => $deliveryId,
                        'stockContainerId' => $stockContainerId,
                        'stockContainerNumber' => $stockContainerNumber,
                    ],
                ]),
            ]),
        );
    }

    public static function noOrderDeliveries(string $orderId): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::NO_ORDER_DELIVERIES,
                    'title' => [
                        'en' => 'No order fulfillment orders',
                        'de' => 'Keine Aufträge',
                    ],
                    'detail' => [
                        'en' => sprintf(
                            'The order with ID "%s" has no fulfillment orders.',
                            $orderId,
                        ),
                        'de' => sprintf(
                            'Die Bestellung mit ID "%s" hat keine Aufträge.',
                            $orderId,
                        ),
                    ],
                    'meta' => ['orderId' => $orderId],
                ]),
            ]),
        );
    }

    public static function noShippingCarrierInstalled(): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::NO_SHIPPING_CARRIER_INSTALLED,
                    'title' => [
                        'en' => 'No shipping carrier installed',
                        'de' => 'Keine Versandintegration installiert',
                    ],
                    'detail' => [
                        'en' => 'There is no shipping carrier installed that can be used to create a shipping label.',
                        'de' => (
                            'Es ist keine Versandintegration installiert, die zur Erstellung eines Versandetiketts '
                            . 'verwendet werden kann.'
                        ),
                    ],
                ]),
            ]),
        );
    }

    /**
     * @param JsonApiError[] $errors
     */
    public static function shipmentCreationFailed(array $errors): self
    {
        $jsonApiErrors = new JsonApiErrors($errors);
        $errorMessage = $jsonApiErrors->getErrorDetailsAsConcatenatedSentences();

        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::SHIPMENT_CREATION_FAILED,
                    'title' => [
                        'en' => 'Shipment creation failed',
                        'de' => 'Erstellen des Versandetiketts fehlgeschlagen',
                    ],
                    'detail' => [
                        'en' => sprintf(
                            'The creation of the shipment failed because an error occurred: %s',
                            $errorMessage,
                        ),
                        'de' => sprintf(
                            'Das Erstellen des Versandetiketts ist fehlgeschlagen, da ein Fehler aufgetreten ist: %s',
                            $errorMessage,
                        ),
                    ],
                    'meta' => ['reasons' => $jsonApiErrors],
                ]),
            ]),
        );
    }

    public static function invalidDevice(
        ?string $pickingProcessDeviceId,
        ?string $pickingProcessDeviceName,
        string $pickingProcessId,
    ): self {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'title' => [
                        'en' => 'Invalid device',
                        'de' => 'Ungültiges Gerät',
                    ],
                    'detail' => [
                        'en' => sprintf(
                            'The picking process is currently processed by another device: %1$s.',
                            $pickingProcessDeviceName ?? '<unknown>',
                        ),
                        'de' => sprintf(
                            'Der Kommissioniervorgang wird auf einem anderen Gerät bearbeitet: %1$s.',
                            $pickingProcessDeviceName ?? '<unknown>',
                        ),
                    ],
                    'meta' => [
                        'deviceId' => $pickingProcessDeviceId,
                        'deviceName' => $pickingProcessDeviceName,
                        'pickingProcessId' => $pickingProcessId,
                    ],
                ]),
            ]),
        );
    }

    public static function pickingProcessNotFound(string $pickingProcessId): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::PICKING_PROCESS_NOT_FOUND,
                    'title' => [
                        'en' => 'Picking process not found',
                        'de' => 'Kommissioniervorgang nicht gefunden',
                    ],
                    'detail' => [
                        'en' => sprintf(
                            'No picking process was found for ID "%s".',
                            $pickingProcessId,
                        ),
                        'de' => sprintf(
                            'Es wurde kein Kommissioniervorgang mit ID "%s" gefunden.',
                            $pickingProcessId,
                        ),
                    ],
                    'meta' => ['pickingProcessId' => $pickingProcessId],
                ]),
            ]),
        );
    }

    public static function deliveryNotFound(string $deliveryId): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::DELIVERY_NOT_FOUND,
                    'title' => [
                        'en' => 'Fulfillment order not found',
                        'de' => 'Auftrag nicht gefunden',
                    ],
                    'detail' => [
                        'en' => sprintf(
                            'No fulfillment order was found for ID "%s".',
                            $deliveryId,
                        ),
                        'de' => sprintf(
                            'Es wurde kein Auftrag mit ID "%s" gefunden.',
                            $deliveryId,
                        ),
                    ],
                    'meta' => ['deliveryId' => $deliveryId],
                ]),
            ]),
        );
    }

    public static function deliveriesNotFound(array $deliveryIds): self
    {
        $concatenatedDeliveryIds = implode(
            ', ',
            $deliveryIds,
        );

        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::DELIVERIES_NOT_FOUND,
                    'title' => [
                        'en' => 'Fulfillment orders could not be found',
                        'de' => 'Aufträge konnten nicht gefunden werden',
                    ],
                    'detail' => [
                        'en' => sprintf(
                            'No fulfillment orders were found for IDs: %s.',
                            $concatenatedDeliveryIds,
                        ),
                        'de' => sprintf(
                            'Es wurden keine Aufträge mit IDs %s gefunden.',
                            $concatenatedDeliveryIds,
                        ),
                    ],
                    'meta' => ['deliveryIds' => $deliveryIds],
                ]),
            ]),
        );
    }

    public static function deliveriesInInvalidStateForDocumentCreationSkipping(array $deliveryIds): self
    {
        $concatenatedDeliveryIds = implode(
            ', ',
            $deliveryIds,
        );

        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::DELIVERIES_IN_INVALID_STATE_FOR_DOCUMENT_CREATION_SKIPPING,
                    'title' => [
                        'en' => 'Fulfillment orders are in an invalid state for skipping the document creation',
                        'de' => 'Aufträge sind in einem ungültigen Status für das Überspringen der Dokumenterstellung',
                    ],
                    'detail' => [
                        'en' => sprintf(
                            'The following fulfillment orders are in an invalid state for skipping the document creation: %s. Only fulfillment orders in the "picked" state can skip the creation of order documents.',
                            $concatenatedDeliveryIds,
                        ),
                        'de' => sprintf(
                            'Die folgenden Aufträge sind in einem ungültigen Status für das Überspringen der Dokumenterstellung: %s. Nur Aufträge im Status "picked" können das Erzeugen von Bestelldokumenten überspringen.',
                            $concatenatedDeliveryIds,
                        ),
                    ],
                    'meta' => ['deliveryIds' => $deliveryIds],
                ]),
            ]),
        );
    }

    public static function noOrderFound(): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::NO_ORDER_FOUND,
                    'title' => [
                        'en' => 'No order found',
                        'de' => 'Keine Bestellung gefunden',
                    ],
                    'detail' => [
                        'en' => 'No order was found that matches the passed criteria.',
                        'de' => 'Es wurde keine Bestellung gefunden, die den gegebenen Kriterien entspricht.',
                    ],
                ]),
            ]),
        );
    }

    /**
     * @param string[] $stockContainerNumbers
     */
    public static function stockContainersAlreadyInUse(array $stockContainerNumbers): self
    {
        $errors = array_map(
            fn(string $stockContainerNumber) => new LocalizableJsonApiError([
                'code' => self::STOCK_CONTAINER_ALREADY_IN_USE,
                'title' => [
                    'en' => 'Stock container already in use',
                    'de' => 'Kiste bereits in Verwendung',
                ],
                'detail' => [
                    'en' => sprintf(
                        'The stock container with number "%s" is already in use.',
                        $stockContainerNumber,
                    ),
                    'de' => sprintf(
                        'Die Kiste mit der Nummer "%s" ist bereits in Verwendung.',
                        $stockContainerNumber,
                    ),
                ],
                'meta' => ['stockContainerNumber' => $stockContainerNumber],
            ]),
            $stockContainerNumbers,
        );

        return new self(new JsonApiErrors($errors));
    }

    public static function missingOrInvalidOrderPickabilityFilter(): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'title' => [
                        'en' => 'Missing or invalid order pickability filter',
                        'de' => 'Fehlender oder ungültiger Bestellauswahlstatusfilter',
                    ],
                    'detail' => [
                        'en' => 'The order pickability filter is missing or invalid.',
                        'de' => 'Der Bestellauswahlstatusfilter fehlt oder ist ungültig.',
                    ],
                ]),
            ]),
        );
    }

    public static function deliveryCannotBeCompletedWithoutStock(string $deliveryId): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'title' => [
                        'en' => 'Fulfillment order cannot be completed without containing stock',
                        'de' => 'Auftrag kann ohne enthaltenen Bestand nicht abgeschlossen werden',
                    ],
                    'detail' => [
                        'en' => 'The fulfillment order cannot be completed because it does not contain any stock.',
                        'de' => 'Der Auftrag kann nicht abgeschlossen werden, da er keinen Bestand enthält.',
                    ],
                    'meta' => [
                        'deliveryId' => $deliveryId,
                    ],
                ]),
            ]),
        );
    }

    public static function carrierConfigurationInvalid(ConfigException $exception, string $deliveryId): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'title' => [
                        'en' => 'Shipment could not be created due to invalid carrier configuration',
                        'de' => 'Versandetikett konnte aufgrund einer ungültigen Versandintegration nicht erstellt werden',
                    ],
                    'detail' => [
                        'en' => sprintf(
                            'The shipment could not be created because the shipping carrier configuration is invalid: %s',
                            $exception->getMessage(),
                        ),
                        'de' => sprintf(
                            'Das Versandetikett konnte nicht erstellt werden, da die Konfiguration der Versandintegration ungültig ist: %s',
                            $exception->getMessage(),
                        ),
                    ],
                    'meta' => [
                        'previousException' => $exception,
                        'deliveryId' => $deliveryId,
                    ],
                ]),
            ]),
            $exception,
        );
    }
}
