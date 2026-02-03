<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\ImportExportProfile;

use InvalidArgumentException;
use LogicException;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Pickware\PickwareErpStarter\Stock\StockAreaType;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class StockImportLocation
{
    private function __construct(
        private readonly StockImportLocationType $stockImportLocationType,
        private readonly ?StockLocationReference $stockLocationReference = null,
        private readonly ?StockArea $stockArea = null,
    ) {}

    public static function stockLocationReference(StockLocationReference $stockLocationReference): self
    {
        if (!in_array($stockLocationReference->getLocationTypeTechnicalName(), [LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION, LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE], true)) {
            throw new InvalidArgumentException('The stock location reference must be a bin location or a warehouse.');
        }

        return new self(StockImportLocationType::StockLocationInWarehouse, $stockLocationReference, null);
    }

    public static function stockArea(StockArea $stockArea): self
    {
        if (!in_array($stockArea->getStockAreaType(), [StockAreaType::Warehouse, StockAreaType::Everywhere], true)) {
            throw new InvalidArgumentException('The stock area must be a warehouse or everywhere.');
        }

        return new self(StockImportLocationType::StockArea, null, $stockArea);
    }

    public function getStockImportLocationType(): StockImportLocationType
    {
        return $this->stockImportLocationType;
    }

    public function getStockLocationReference(): StockLocationReference
    {
        if ($this->stockImportLocationType !== StockImportLocationType::StockLocationInWarehouse) {
            throw new InvalidArgumentException('This stock import location does not reference a stock location reference.');
        }

        return $this->stockLocationReference;
    }

    public function getStockArea(): StockArea
    {
        if ($this->stockImportLocationType !== StockImportLocationType::StockArea) {
            throw new InvalidArgumentException('This stock import location does not reference a stock area.');
        }

        return $this->stockArea;
    }

    public function getStockImportGranularity(): StockImportGranularity
    {
        if ($this->stockImportLocationType === StockImportLocationType::StockLocationInWarehouse) {
            return StockImportGranularity::StockLocation;
        }

        return match ($this->stockArea->getStockAreaType()) {
            StockAreaType::Warehouse => StockImportGranularity::Warehouse,
            StockAreaType::Everywhere => StockImportGranularity::Product,
            StockAreaType::Warehouses => throw new LogicException('The stock area "warehouses" is not supported as an import granularity.'),
        };
    }
}
