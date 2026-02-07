<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderPickability;

use Pickware\PickwareErpStarter\OrderPickability\Model\OrderPickabilityCollection;
use Pickware\PickwareErpStarter\OrderPickability\Model\OrderPickabilityEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;

class OrderPickabilityCriteriaFilterContext
{
    /**
     * @var string[]
     */
    private array $warehouseIds = [];

    /**
     * @var callable[]
     */
    private array $pickabilityStatusFilterInjections = [];

    /**
     * @var callable[]
     */
    private array $unknownPickabilityFilterInjections = [];

    /**
     * @param string[] $validWarehouseIds
     * @param string[] $validPickabilityStatus
     */
    public function addPickabilityStatusFilter(
        array $validWarehouseIds,
        array $validPickabilityStatus,
        callable $applyFilter,
    ): void {
        $this->warehouseIds = array_merge($this->warehouseIds, $validWarehouseIds);

        $this->pickabilityStatusFilterInjections[] = function(OrderPickabilityCollection $orderPickabilities) use (
            $validWarehouseIds,
            $validPickabilityStatus,
            $applyFilter
        ): void {
            $orderIds = $orderPickabilities
                ->filter(fn(OrderPickabilityEntity $pickability) => (
                    in_array($pickability->getWarehouseId(), $validWarehouseIds)
                    && in_array($pickability->getOrderPickabilityStatus(), $validPickabilityStatus)
                ))
                ->map(fn(OrderPickabilityEntity $pickability) => $pickability->getOrderId());

            $applyFilter(new EqualsAnyFilter('order.id', array_values($orderIds)));
        };
    }

    public function hasFiltersToApply(): bool
    {
        return count($this->pickabilityStatusFilterInjections) + count($this->unknownPickabilityFilterInjections) > 0;
    }

    public function addUnknownPickabilityFilter(callable $applyFilter): void
    {
        $this->unknownPickabilityFilterInjections[] = function(array $orderIdsWithoutPickabilities) use (
            $applyFilter
        ): void {
            $applyFilter(new EqualsAnyFilter('order.id', $orderIdsWithoutPickabilities));
        };
    }

    public function applyFilters(OrderPickabilityCalculator $orderPickabilityCalculator): void
    {
        if (count($this->pickabilityStatusFilterInjections) > 0) {
            $orderPickabilities = $orderPickabilityCalculator->calculateOrderPickabilitiesForWarehouses(
                array_unique($this->warehouseIds),
            );
            foreach ($this->pickabilityStatusFilterInjections as $filterInjection) {
                $filterInjection($orderPickabilities);
            }
        }
        if (count($this->unknownPickabilityFilterInjections) > 0) {
            $orderIdsWithoutPickabilities = $orderPickabilityCalculator->getOrderIdsWithoutPickabilities();
            foreach ($this->unknownPickabilityFilterInjections as $filterInjection) {
                $filterInjection($orderIdsWithoutPickabilities);
            }
        }
    }

    public function merge(OrderPickabilityCriteriaFilterContext $otherContext): void
    {
        $this->warehouseIds = array_merge($this->warehouseIds, $otherContext->warehouseIds);
        $this->pickabilityStatusFilterInjections = array_merge(
            $this->pickabilityStatusFilterInjections,
            $otherContext->pickabilityStatusFilterInjections,
        );
        $this->unknownPickabilityFilterInjections = array_merge(
            $this->unknownPickabilityFilterInjections,
            $otherContext->unknownPickabilityFilterInjections,
        );
    }
}
