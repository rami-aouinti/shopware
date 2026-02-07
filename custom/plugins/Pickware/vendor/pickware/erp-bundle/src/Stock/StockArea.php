<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock;

use InvalidArgumentException;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
readonly class StockArea
{
    private function __construct(
        private StockAreaType $stockAreaType,
        private array $warehouseIds,
    ) {}

    public static function everywhere(): self
    {
        return new self(StockAreaType::Everywhere, []);
    }

    public static function warehouse(string $warehouseId): self
    {
        return new self(StockAreaType::Warehouse, [$warehouseId]);
    }

    /**
     * @param string[] $warehouseIds
     */
    public static function warehouses(array $warehouseIds): self
    {
        if (count($warehouseIds) === 0) {
            throw new InvalidArgumentException('At least one warehouse ID must be provided.');
        }

        return new self(StockAreaType::Warehouses, $warehouseIds);
    }

    public function getStockAreaType(): StockAreaType
    {
        return $this->stockAreaType;
    }

    public function getWarehouseId(): string
    {
        return match ($this->stockAreaType) {
            StockAreaType::Warehouse => $this->warehouseIds[0],
            StockAreaType::Warehouses, StockAreaType::Everywhere => throw new LogicException('Can only get a single warehouse ID if the stock area type is Warehouse.'),
        };
    }

    /**
     * @return string[]
     */
    public function getWarehouseIds(): array
    {
        return $this->warehouseIds;
    }

    public function isEverywhere(): bool
    {
        return $this->stockAreaType === StockAreaType::Everywhere;
    }

    public function isWarehouse(): bool
    {
        return $this->stockAreaType === StockAreaType::Warehouse;
    }

    public function isWarehouses(): bool
    {
        return $this->stockAreaType === StockAreaType::Warehouses;
    }
}
