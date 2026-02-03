<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\StockingProcess;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorResponse;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\HttpUtils\JsonApi\JsonApiErrorsSerializable;
use Pickware\PickwareWms\Device\Device;

class StockingProcessException extends Exception implements JsonApiErrorsSerializable
{
    public const ERROR_CODE_NAMESPACE = 'PICKWARE_WMS__STOCKING_PROCESS__';
    public const PENDING_STOCK = self::ERROR_CODE_NAMESPACE . 'PENDING_STOCK';
    public const INVALID_GOODS_RECEIPT_STATE = self::ERROR_CODE_NAMESPACE . 'INVALID_GOODS_RECEIPT_STATE';
    public const STOCKING_PROCESS_IN_PROGRESS_BY_ANOTHER_DEVICE = self::ERROR_CODE_NAMESPACE . 'STOCKING_PROCESS_IN_PROGRESS_BY_ANOTHER_DEVICE';
    public const INVALID_STOCKING_PROCESS_STATE = self::ERROR_CODE_NAMESPACE . 'INVALID_STOCKING_PROCESS_STATE';
    public const STOCKING_PROCESS_NOT_FOUND = self::ERROR_CODE_NAMESPACE . 'STOCKING_PROCESS_NOT_FOUND';
    public const NOT_ENOUGH_STOCK = self::ERROR_CODE_NAMESPACE . 'NOT_ENOUGH_STOCK';
    public const SOURCE_ALREADY_IN_USE = self::ERROR_CODE_NAMESPACE . 'SOURCE_ALREADY_IN_USE';
    public const GOODS_RECEIPT_ALREADY_IN_USE = self::ERROR_CODE_NAMESPACE . 'GOODS_RECEIPT_ALREADY_IN_USE';

    private JsonApiErrors $jsonApiErrors;

    public function __construct(JsonApiErrors $jsonApiErrors)
    {
        $this->jsonApiErrors = $jsonApiErrors;
        parent::__construct($jsonApiErrors->getThrowableMessage());
    }

    public function serializeToJsonApiErrors(): JsonApiErrors
    {
        return $this->jsonApiErrors;
    }

    public function toJsonApiErrorResponse(?int $status = null): JsonApiErrorResponse
    {
        return $this->jsonApiErrors->toJsonApiErrorResponse($status);
    }

    public static function pendingStock(string $stockingProcessId): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::PENDING_STOCK,
                    'title' => [
                        'en' => 'Stocking process contains stock',
                        'de' => 'Einlagerungsvorgang enthält Bestand',
                    ],
                    'detail' => [
                        'en' => 'The stocking process still contains pending stock for stocking.',
                        'de' => 'Der Einlagerungsvorgang enthält noch Bestand zum Einlagern.',
                    ],
                    'meta' => [
                        'stockingProcessId' => $stockingProcessId,
                    ],
                ]),
            ]),
        );
    }

    public static function invalidGoodsReceiptState(string $number, string $allowedState, string $actualState): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::INVALID_GOODS_RECEIPT_STATE,
                    'title' => [
                        'en' => 'Invalid goods receipt state',
                        'de' => 'Ungültiger Status des Wareneingang',
                    ],
                    'detail' => [
                        'en' => sprintf(
                            'The goods receipt with number %s is in an invalid state.' .
                            "\nAllowed state: %s\nActual state: %s",
                            $number,
                            $allowedState,
                            $actualState,
                        ),
                        'de' => sprintf(
                            'Der Wareneingang mit Nummer %s befindet sich in einem ungültigen Status.' .
                            "\\nErlaubter Status: %s\\nAktueller Status: %s",
                            $number,
                            $allowedState,
                            $actualState,
                        ),
                    ],
                ]),
            ]),
        );
    }

    public static function stockingProcessInProgressByAnotherDevice(
        Device $device,
        string $stockingProcessId,
        ?string $deviceNameOfStockingProcess,
        ?string $deviceIdOfStockingProcess,
    ): self {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::STOCKING_PROCESS_IN_PROGRESS_BY_ANOTHER_DEVICE,
                    'title' => [
                        'de' => 'Stocking process already in progress',
                        'en' => 'Einlagerungsvorgang wird bereits bearbeitet',
                    ],
                    'detail' => [
                        'de' => sprintf(
                            'Der Einlagerungsvorgang wird bereits von einem anderen Gerät mit Namen "%s" bearbeitet.',
                            $deviceNameOfStockingProcess ?? '(unknown)',
                        ),
                        'en' => sprintf(
                            'The stocking process is already in progress by another device with name: "%s"',
                            $deviceNameOfStockingProcess ?? '(unknown)',
                        ),
                    ],
                    'meta' => [
                        'device' => $device->toPayload(),
                        'stockingProcessId' => $stockingProcessId,
                        'stockingProcessDevice' => [
                            'id' => $deviceIdOfStockingProcess,
                            'name' => $deviceNameOfStockingProcess,
                        ],
                    ],
                ]),
            ]),
        );
    }

    public static function invalidStockingProcessState(
        string $stockingProcessId,
        string $currentStateName,
        array $expectedStateNames,
    ): self {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::INVALID_STOCKING_PROCESS_STATE,
                    'title' => [
                        'de' => 'Ungültiger Einlagerungsstatus',
                        'en' => 'Invalid stocking process state',
                    ],
                    'detail' => [
                        'de' => sprintf(
                            'Der Einlagerungsvorgang befindet sich in einem ungültigen Status. '
                            . 'Aktueller Status: "%s". Gültige Status: %s.',
                            $currentStateName,
                            implode(
                                ', ',
                                array_map(fn(string $stateName) => sprintf('"%s"', $stateName), $expectedStateNames),
                            ),
                        ),
                        'en' => sprintf(
                            'The stocking process is in an invalid state. '
                            . 'Current state is "%s". Valid states are %s.',
                            $currentStateName,
                            implode(
                                ', ',
                                array_map(fn(string $stateName) => sprintf('"%s"', $stateName), $expectedStateNames),
                            ),
                        ),
                    ],
                    'meta' => [
                        'stockingProcessId' => $stockingProcessId,
                        'currentStateName' => $currentStateName,
                        'expectedStateNames' => $expectedStateNames,
                    ],
                ]),
            ]),
        );
    }

    public static function stockingProcessNotFound(string $stockingProcessId): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::STOCKING_PROCESS_NOT_FOUND,
                    'title' => [
                        'en' => 'Stocking process not found',
                        'de' => 'Einlagerungsvorgang nicht gefunden',
                    ],
                    'detail' => [
                        'en' => 'The stocking process was not found. It may have been completed on another device.',
                        'de' => 'Der Einlagerungsvorgang wurde nicht gefunden. Möglicherweise wurde er bereits auf ' .
                            'einem anderen Gerät abgeschlossen.',
                    ],
                    'meta' => ['stockingProcessId' => $stockingProcessId],
                ]),
            ]),
        );
    }

    public static function sourceAlreadyInUse(array $sourceIds): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::SOURCE_ALREADY_IN_USE,
                    'title' => [
                        'en' => 'Source already in use',
                        'de' => 'Herkunft bereits in Verwendung',
                    ],
                    'detail' => [
                        'en' => 'At least one selected source of the stocking process is already in use by another' .
                            ' stocking process.',
                        'de' => 'Mindestens eine ausgewählte Quelle des Einlagerungsvorgangs wird bereits von einem' .
                            ' anderen Einlagerungsvorgang eingelagert.',
                    ],
                    'meta' => ['existingStockingProcessSourceIds' => $sourceIds],
                ]),
            ]),
        );
    }

    public static function goodsReceiptAlreadyInUse(string $goodsReceiptNumber): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::GOODS_RECEIPT_ALREADY_IN_USE,
                    'title' => [
                        'en' => 'Goods receipt already in use',
                        'de' => 'Wareneingang bereits in Verwendung',
                    ],
                    'detail' => [
                        'en' => sprintf(
                            'The goods receipt with number "%s" is already stocked by another stocking process.',
                            $goodsReceiptNumber,
                        ),
                        'de' => sprintf(
                            'Der Wareneingang mit Nummer "%s" wird bereits von einem anderen Einlagerungsvorgang' .
                            ' eingelagert.',
                            $goodsReceiptNumber,
                        ),
                    ],
                    'meta' => ['goodsReceiptNumber' => $goodsReceiptNumber],
                ]),
            ]),
        );
    }

    public static function stockContainerAlreadyInUse(string $stockContainerNumber): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'title' => [
                        'en' => 'Stock container already in use',
                        'de' => 'Kiste bereits in Verwendung',
                    ],
                    'detail' => [
                        'en' => sprintf(
                            'The stock container "%s" is already stocked by another stocking process.',
                            $stockContainerNumber,
                        ),
                        'de' => sprintf(
                            'Der Lagerbehälter "%s" wird bereits von einem anderen Einlagerungsvorgang eingelagert.',
                            $stockContainerNumber,
                        ),
                    ],
                    'meta' => ['stockContainerNumber' => $stockContainerNumber],
                ]),
            ]),
        );
    }

    public static function notEnoughStock(string $stockingProcessId): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::NOT_ENOUGH_STOCK,
                    'title' => [
                        'en' => 'Not enough stock',
                        'de' => 'Zu wenig Bestand',
                    ],
                    'detail' => [
                        'en' => 'The stocking process does not have enough stock to restock the product in the' .
                            ' requested quantity.',
                        'de' => 'Im Einlagerungsvorgang befindet sich nicht genug Bestand um das Produkt in der' .
                            ' ausgewählten Menge einzulagern',
                    ],
                    'meta' => ['stockingProcessId' => $stockingProcessId],
                ]),
            ]),
        );
    }
}
