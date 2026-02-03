<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockApi;

use InvalidArgumentException;
use JsonSerializable;
use LogicException;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\Model\SpecialStockLocationDefinition;
use ReturnTypeWillChange;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @phpstan-type StockLocationReferenceData = string|array{
 *     binLocation?: array{id: string},
 *     warehouse?: array{id: string},
 *     order?: array{id: string},
 *     returnOrder?: array{id: string},
 *     stockContainer?: array{id: string},
 *     goodsReceipt?: array{id: string},
 * }
 */
#[Exclude]
class StockLocationReference implements JsonSerializable
{
    public const JSON_SCHEMA_FILE = __DIR__ . '/../Resources/json-schema/stock-location.schema.json';
    public const POSITION_SOURCE = 'source';
    public const POSITION_DESTINATION = 'destination';
    public const POSITIONS = [
        self::POSITION_SOURCE,
        self::POSITION_DESTINATION,
    ];

    private string $locationTypeTechnicalName;

    /**
     * Name of the field that represents the primary key, e.g. "id", "technicalName"
     */
    private string $primaryKeyFieldName;

    /**
     * Type of the field used as primary key, e.g. UUID, technical name
     */
    private PrimaryKeyFieldType $primaryKeyFieldType;

    /**
     * Value of the field that represents the primary key, e.g. a UUID of a bin location or the technical
     *      name of a special stock location
     */
    private string $primaryKey;

    /**
     * Name of the primary key's version field, if the primary key references a versioned entity
     */
    private ?string $primaryKeyVersionFieldName = null;

    private ?array $snapshot = null;

    private function __construct(
        string $locationTypeTechnicalName,
        string $primaryKeyFieldName,
        PrimaryKeyFieldType $primaryKeyFieldType,
        string $primaryKey,
        ?string $primaryKeyVersionFieldName = null,
    ) {
        $this->locationTypeTechnicalName = $locationTypeTechnicalName;
        $this->primaryKeyFieldName = $primaryKeyFieldName;
        $this->primaryKeyFieldType = $primaryKeyFieldType;
        $this->primaryKey = $primaryKey;
        $this->primaryKeyVersionFieldName = $primaryKeyVersionFieldName;
    }

    /**
     * Jsonserializes this stock location reference. Corresponds to the supported input of the self::create function.
     *
     * @return StockLocationReferenceData
     */
    #[ReturnTypeWillChange]
    public function jsonSerialize()/*: string|array */
    {
        if ($this->locationTypeTechnicalName === LocationTypeDefinition::TECHNICAL_NAME_SPECIAL_STOCK_LOCATION) {
            return $this->primaryKeyFieldName;
        }

        return [
            self::snakeToCamelCase($this->locationTypeTechnicalName) => [
                $this->getPrimaryKeyFieldName() => $this->getPrimaryKey(),
            ],
        ];
    }

    public static function binLocation(string $id): self
    {
        return new self(
            locationTypeTechnicalName: LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION,
            primaryKeyFieldName: 'id',
            primaryKeyFieldType: PrimaryKeyFieldType::Uuid,
            primaryKey: $id,
        );
    }

    public static function warehouse(string $id): self
    {
        return new self(
            locationTypeTechnicalName: LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE,
            primaryKeyFieldName: 'id',
            primaryKeyFieldType: PrimaryKeyFieldType::Uuid,
            primaryKey: $id,
        );
    }

    public static function specialStockLocation(string $technicalName): self
    {
        return new self(
            locationTypeTechnicalName: LocationTypeDefinition::TECHNICAL_NAME_SPECIAL_STOCK_LOCATION,
            primaryKeyFieldName: 'technicalName',
            primaryKeyFieldType: PrimaryKeyFieldType::TechnicalName,
            primaryKey: $technicalName,
        );
    }

    public static function order(string $id): self
    {
        return new self(
            locationTypeTechnicalName: LocationTypeDefinition::TECHNICAL_NAME_ORDER,
            primaryKeyFieldName: 'id',
            primaryKeyFieldType: PrimaryKeyFieldType::Uuid,
            primaryKey: $id,
            primaryKeyVersionFieldName: 'versionId',
        );
    }

    public static function returnOrder(string $id): self
    {
        return new self(
            locationTypeTechnicalName: LocationTypeDefinition::TECHNICAL_NAME_RETURN_ORDER,
            primaryKeyFieldName: 'id',
            primaryKeyFieldType: PrimaryKeyFieldType::Uuid,
            primaryKey: $id,
            primaryKeyVersionFieldName: 'versionId',
        );
    }

    public static function stockContainer(string $id): self
    {
        return new self(
            locationTypeTechnicalName: LocationTypeDefinition::TECHNICAL_NAME_STOCK_CONTAINER,
            primaryKeyFieldName: 'id',
            primaryKeyFieldType: PrimaryKeyFieldType::Uuid,
            primaryKey: $id,
        );
    }

    public static function goodsReceipt(string $id): self
    {
        return new self(
            locationTypeTechnicalName: LocationTypeDefinition::TECHNICAL_NAME_GOODS_RECEIPT,
            primaryKeyFieldName: 'id',
            primaryKeyFieldType: PrimaryKeyFieldType::Uuid,
            primaryKey: $id,
        );
    }

    public static function unknown(): self
    {
        return self::specialStockLocation(SpecialStockLocationDefinition::TECHNICAL_NAME_UNKNOWN);
    }

    public static function initialization(): self
    {
        return self::specialStockLocation(SpecialStockLocationDefinition::TECHNICAL_NAME_INITIALIZATION);
    }

    public static function productTotalStockChange(): self
    {
        return self::specialStockLocation(SpecialStockLocationDefinition::TECHNICAL_NAME_PRODUCT_TOTAL_STOCK_CHANGE);
    }

    public static function productAvailableStockChange(): self
    {
        return self::specialStockLocation(SpecialStockLocationDefinition::TECHNICAL_NAME_PRODUCT_AVAILABLE_STOCK_CHANGE);
    }

    public static function stockCorrection(): self
    {
        return self::specialStockLocation(SpecialStockLocationDefinition::TECHNICAL_NAME_STOCK_CORRECTION);
    }

    public static function import(): self
    {
        return self::specialStockLocation(SpecialStockLocationDefinition::TECHNICAL_NAME_IMPORT);
    }

    public static function shopwareMigration(): self
    {
        return self::specialStockLocation(SpecialStockLocationDefinition::TECHNICAL_NAME_SHOPWARE_MIGRATION);
    }

    /**
     * @param StockLocationReferenceData $stockLocation
     */
    public static function create($stockLocation): self
    {
        if (!is_array($stockLocation) && !is_string($stockLocation)) {
            throw new InvalidArgumentException('Argument must be of type string or array.');
        }
        if (is_string($stockLocation)) {
            return StockLocationReference::specialStockLocation($stockLocation);
        }
        if (array_key_exists('warehouse', $stockLocation)) {
            return StockLocationReference::warehouse($stockLocation['warehouse']['id']);
        }
        if (array_key_exists('binLocation', $stockLocation)) {
            return StockLocationReference::binLocation($stockLocation['binLocation']['id']);
        }
        if (array_key_exists('order', $stockLocation)) {
            return StockLocationReference::order($stockLocation['order']['id']);
        }
        if (array_key_exists('stockContainer', $stockLocation)) {
            return StockLocationReference::stockContainer($stockLocation['stockContainer']['id']);
        }
        if (array_key_exists('returnOrder', $stockLocation)) {
            return StockLocationReference::returnOrder($stockLocation['returnOrder']['id']);
        }
        if (array_key_exists('goodsReceipt', $stockLocation)) {
            return StockLocationReference::goodsReceipt($stockLocation['goodsReceipt']['id']);
        }

        // Due to the type checks and "specialStockLocation from string" wildcard, only an invalid array remains
        throw new InvalidArgumentException(sprintf(
            'Stock location could not be created from array with key(s): %s.',
            implode(', ', array_keys($stockLocation)),
        ));
    }

    public function hash(): string
    {
        return sha1($this->locationTypeTechnicalName . '#' . $this->primaryKey);
    }

    public function toSourcePayload(): array
    {
        return $this->toPayloadWithPrefix(self::POSITION_SOURCE);
    }

    public function toDestinationPayload(): array
    {
        return $this->toPayloadWithPrefix(self::POSITION_DESTINATION);
    }

    public function toPayload(): array
    {
        return $this->toPayloadWithPrefix('');
    }

    private function toPayloadWithPrefix(string $prefix): array
    {
        $locationTypeTechnicalKeyName = lcfirst(sprintf('%sLocationTypeTechnicalName', $prefix));
        $snapshotKeyName = lcfirst(sprintf('%sLocationSnapshot', $prefix));
        $referencingKeyName = lcfirst(sprintf(
            '%s%s%s',
            $prefix,
            ucfirst(self::snakeToCamelCase($this->locationTypeTechnicalName)),
            ucfirst(self::snakeToCamelCase($this->primaryKeyFieldName)),
        ));

        $payload = [
            $locationTypeTechnicalKeyName => $this->locationTypeTechnicalName,
            $snapshotKeyName => $this->snapshot,
            $referencingKeyName => $this->primaryKey,
        ];

        if ($this->primaryKeyVersionFieldName) {
            $referencingVersionKeyName = lcfirst(sprintf(
                '%s%s%s',
                $prefix,
                ucfirst(self::snakeToCamelCase($this->locationTypeTechnicalName)),
                ucfirst(self::snakeToCamelCase($this->primaryKeyVersionFieldName)),
            ));
            $payload[$referencingVersionKeyName] = Defaults::LIVE_VERSION;
        }

        return $payload;
    }

    public function getFilterForStockDefinition(): EqualsFilter
    {
        // Due to a bug in the DAL, filtering via the foreign key is not possible when it is not a UUID. Instead,
        // the primary key of the referenced entity must be used in such cases. For UUIDs, this approach should be
        // avoided as it introduces an extra join, potentially causing the selection of additional tables and
        // creating more locks than necessary when the filters are used to establish a lock.
        $fieldName = match ($this->primaryKeyFieldType) {
            PrimaryKeyFieldType::Uuid => self::snakeToCamelCase($this->locationTypeTechnicalName) . ucfirst($this->primaryKeyFieldName),
            PrimaryKeyFieldType::TechnicalName => self::snakeToCamelCase($this->locationTypeTechnicalName) . '.' . $this->primaryKeyFieldName,
        };

        return new EqualsFilter($fieldName, $this->primaryKey);
    }

    public function getFilterForProductStockLocationMapping(): EqualsFilter
    {
        $fieldName = match ($this->locationTypeTechnicalName) {
            LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE => 'warehouseId',
            LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION => 'binLocationId',
            default => throw new LogicException(sprintf('The stock location reference of type %s has no corresponding product stock location mapping', $this->locationTypeTechnicalName)),
        };

        return new EqualsFilter($fieldName, $this->primaryKey);
    }

    private static function snakeToCamelCase($string): string
    {
        $converter = new CamelCaseToSnakeCaseNameConverter();

        return $converter->denormalize($string);
    }

    private static function camelCaseToSnakeCase($string): string
    {
        $converter = new CamelCaseToSnakeCaseNameConverter();

        return $converter->normalize($string);
    }

    public function getSnapshot(): ?array
    {
        return $this->snapshot;
    }

    public function setSnapshot(?array $snapshot): void
    {
        $this->snapshot = $snapshot;
    }

    public function getLocationTypeTechnicalName(): string
    {
        return $this->locationTypeTechnicalName;
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    public function getPrimaryKeyFieldName(): string
    {
        return $this->primaryKeyFieldName;
    }

    public function getDatabasePrimaryKeyFieldName(string $stockLocationPosition): string
    {
        $this->validateStockMovementDirectionArgument($stockLocationPosition);

        return sprintf(
            '%s_%s_%s',
            $stockLocationPosition,
            $this->locationTypeTechnicalName,
            self::camelCaseToSnakeCase($this->primaryKeyFieldName),
        );
    }

    public function getDatabaseVersionFieldName(string $stockLocationPosition): ?string
    {
        $this->validateStockMovementDirectionArgument($stockLocationPosition);
        if (!$this->primaryKeyVersionFieldName) {
            return null;
        }

        return sprintf(
            '%s_%s_%s',
            $stockLocationPosition,
            $this->locationTypeTechnicalName,
            self::camelCaseToSnakeCase($this->primaryKeyVersionFieldName),
        );
    }

    public function isWarehouse(): bool
    {
        return $this->locationTypeTechnicalName === LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE;
    }

    /**
     * @return string The warehouse ID of this stock location reference if it references a warehouse.
     */
    public function getWarehouseId(): string
    {
        if (!$this->isWarehouse()) {
            throw new InvalidArgumentException('This stock location does not reference a warehouse.');
        }

        return $this->primaryKey;
    }

    public function isBinLocation(): bool
    {
        return $this->locationTypeTechnicalName === LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION;
    }

    /**
     * @return string The bin location ID of this stock location reference if it references a bin location.
     */
    public function getBinLocationId(): string
    {
        if (!$this->isBinLocation()) {
            throw new InvalidArgumentException('This stock location does not reference a bin location.');
        }

        return $this->primaryKey;
    }

    public function isGoodsReceipt(): bool
    {
        return $this->locationTypeTechnicalName === LocationTypeDefinition::TECHNICAL_NAME_GOODS_RECEIPT;
    }

    /**
     * @return string The goods receipt ID of this stock location reference if it references a goods receipt.
     */
    public function getGoodsReceiptId(): string
    {
        if (!$this->isGoodsReceipt()) {
            throw new InvalidArgumentException('This stock location reference does not reference a goods receipt.');
        }

        return $this->primaryKey;
    }

    public function equals(StockLocationReference $other): bool
    {
        return
            $this->locationTypeTechnicalName === $other->getLocationTypeTechnicalName()
            && $this->primaryKey === $other->getPrimaryKey();
    }

    private function validateStockMovementDirectionArgument(string $stockLocationPosition): void
    {
        if (!in_array($stockLocationPosition, self::POSITIONS)) {
            throw new InvalidArgumentException(sprintf(
                'Stock location position "%s" is invalid. Valid positions: %s.',
                $stockLocationPosition,
                implode(', ', self::POSITIONS),
            ));
        }
    }

    public function isReturnOrder(): bool
    {
        return $this->locationTypeTechnicalName === LocationTypeDefinition::TECHNICAL_NAME_RETURN_ORDER;
    }

    /**
     * @return string The return order ID of this stock location reference if it references a return order.
     */
    public function getReturnOrderId(): string
    {
        if (!$this->isReturnOrder()) {
            throw new InvalidArgumentException('This stock location reference does not reference a return order.');
        }

        return $this->primaryKey;
    }

    public function isOrder(): bool
    {
        return $this->locationTypeTechnicalName === LocationTypeDefinition::TECHNICAL_NAME_ORDER;
    }

    /**
     * @return string The order ID of this stock location reference if it references an order.
     */
    public function getOrderId(): string
    {
        if (!$this->isOrder()) {
            throw new InvalidArgumentException('This stock location reference does not reference an order.');
        }

        return $this->primaryKey;
    }

    public function isStockContainer(): bool
    {
        return $this->locationTypeTechnicalName === LocationTypeDefinition::TECHNICAL_NAME_STOCK_CONTAINER;
    }

    /**
     * @return string The stock container ID of this stock location reference if it references a stock container.
     */
    public function getStockContainerId(): string
    {
        if (!$this->isStockContainer()) {
            throw new InvalidArgumentException('This stock location reference does not reference a stock container.');
        }

        return $this->primaryKey;
    }

    public function isUnknown(): bool
    {
        return $this->locationTypeTechnicalName === LocationTypeDefinition::TECHNICAL_NAME_SPECIAL_STOCK_LOCATION
            && $this->primaryKey === SpecialStockLocationDefinition::TECHNICAL_NAME_UNKNOWN;
    }

    public function isSpecialStockLocation(): bool
    {
        return $this->locationTypeTechnicalName === LocationTypeDefinition::TECHNICAL_NAME_SPECIAL_STOCK_LOCATION;
    }

    /**
     * Internal locations will count to the total product stock shown in the administration. Internal locations are
     * assigned to a warehouse and count to the according warehouse stock.
     *
     * @return bool Whether stock on this stock location will count to the total product stock.
     */
    public function isInternal(): bool
    {
        return in_array($this->locationTypeTechnicalName, LocationTypeDefinition::TECHNICAL_NAMES_INTERNAL, true);
    }

    /**
     * Short description for technical error messages
     */
    public function getTechnicalDescription(): string
    {
        return sprintf('%s with %s: %s', $this->locationTypeTechnicalName, $this->primaryKeyFieldName, $this->primaryKey);
    }
}
