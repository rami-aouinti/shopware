<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockLocationSorting;

use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\StockApi\StockLocationConfigurationService;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Sorts stock locations by their warehouse and bin location properties.
 * It applies the following sorting rules in the following order:
 * 1. Sort locations in default warehouse to the beginning
 * 2. Sort locations in non default warehouses by the warehouse creation date
 * 3. Sort unknown bin locations to the end of each warehouse
 * 4. Sort by bin location properties given in $sortProperties ASC
 */
#[Exclude]
class BinLocationPropertyStockLocationSorter
{
    /**
     * @param BinLocationProperty[] $sortByProperties
     */
    public function __construct(
        private readonly StockLocationConfigurationService $stockLocationConfigurationService,
        private readonly array $sortByProperties,
    ) {}

    public static function createBinLocationCodeStockLocationSorter(
        StockLocationConfigurationService $stockLocationConfigurationService,
    ): self {
        return new self(
            $stockLocationConfigurationService,
            [BinLocationProperty::Code],
        );
    }

    public static function createBinLocationPositionStockLocationSorter(
        StockLocationConfigurationService $stockLocationConfigurationService,
    ): self {
        return new self(
            $stockLocationConfigurationService,
            [
                BinLocationProperty::Position,
                BinLocationProperty::Code,
            ],
        );
    }

    /**
     * @param ImmutableCollection<StockLocationReference> $stockLocationReferences
     * @return ImmutableCollection<StockLocationReference>
     */
    public function sort(ImmutableCollection $stockLocationReferences, Context $context): ImmutableCollection
    {
        $stockLocationConfigurations = $this->stockLocationConfigurationService->getStockLocationConfigurations(
            $stockLocationReferences,
            $context,
        );

        return $stockLocationReferences->sorted(
            function(StockLocationReference $lhsLocation, StockLocationReference $rhsLocation) use ($stockLocationConfigurations) {
                $lhsConfig = $stockLocationConfigurations->getForStockLocation($lhsLocation);
                $rhsConfig = $stockLocationConfigurations->getForStockLocation($rhsLocation);

                // Priority 1: Sort all locations in the default warehouse to the beginning
                $sortingValue = $rhsConfig->getIsInDefaultWarehouse() <=> $lhsConfig->getIsInDefaultWarehouse();
                if ($sortingValue !== 0) {
                    return $sortingValue;
                }

                // Priority 2: Sort locations in non default warehouses by the warehouse creation date
                // Two date times can not be compared via `===` or `!==` because they are never considered the same.
                $sortingValue = $lhsConfig->getWarehouseCreationDate() <=> $rhsConfig->getWarehouseCreationDate();
                if ($sortingValue !== 0) {
                    return $sortingValue;
                }

                // Priority 3: Sort unknown bin locations to the end of each warehouse
                $sortingValue = $lhsLocation->isWarehouse() <=> $rhsLocation->isWarehouse();
                if ($sortingValue !== 0) {
                    return $sortingValue;
                }

                // Priority 4: Sort by provided properties
                foreach ($this->sortByProperties as $sortByProperty) {
                    $lhsValue = $sortByProperty->getPropertyValue($lhsConfig);
                    $rhsValue = $sortByProperty->getPropertyValue($rhsConfig);

                    $sortingValue = $sortByProperty->compare($lhsValue, $rhsValue);
                    if ($sortingValue !== 0) {
                        return $sortingValue;
                    }
                }

                return 0;
            },
        );
    }
}
