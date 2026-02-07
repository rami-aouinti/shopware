<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\OrderPickability;

use BadMethodCallException;
use Pickware\PhpStandardLibrary\Collection\Map;
use Pickware\PickwareErpStarter\OrderPickability\Model\OrderPickabilityCollection;
use Pickware\PickwareErpStarter\OrderPickability\OrderPickabilityCalculator;

class CachingOrderPickabilityCalculator extends OrderPickabilityCalculator
{
    /**
     * @var array<string, OrderPickabilityCollection> $cachedPickabilities
     */
    private array $cachedPickabilities = [];

    public function __construct(private readonly OrderPickabilityCalculator $orderPickabilityCalculator) {}

    /**
     * @param string[] $warehouseIds
     */
    public function calculateOrderPickabilitiesForWarehouses(array $warehouseIds): OrderPickabilityCollection
    {
        if (count($warehouseIds) === 0) {
            return new OrderPickabilityCollection();
        }

        sort($warehouseIds);
        $key = implode($warehouseIds);
        if (!isset($this->cachedPickabilities[$key])) {
            $this->cachedPickabilities[$key] = $this->orderPickabilityCalculator->calculateOrderPickabilitiesForWarehouses($warehouseIds);
        }

        return $this->cachedPickabilities[$key];
    }

    public function calculateProductPickabilitiesForOrderAndWarehouse(string $orderId, string $warehouseId): Map
    {
        // We actually know that "calculateProductPickabilitiesForOrderAndWarehouse" is only called through a new
        // api endpoint that was added in pickware-erp-bundle alongside "calculateProductPickabilitiesForOrderAndWarehouse".
        // So method missing should never happen in production.
        // @phpstan-ignore function.alreadyNarrowedType (Method does not exist in older class versions)
        if (!method_exists($this->orderPickabilityCalculator, 'calculateProductPickabilitiesForOrderAndWarehouse')) {
            throw new BadMethodCallException('Method calculateProductPickabilitiesForOrderAndWarehouse should not be called because the method is missing in the decorated OrderPickabilityCalculator. Update erp-bundle first.');
        }

        return $this->orderPickabilityCalculator->calculateProductPickabilitiesForOrderAndWarehouse($orderId, $warehouseId);
    }

    public function calculateOrderPickabilitiesForOrders(array $orderIds): OrderPickabilityCollection
    {
        return $this->orderPickabilityCalculator->calculateOrderPickabilitiesForOrders($orderIds);
    }

    public function getOrderIdsWithoutPickabilities(): array
    {
        return $this->orderPickabilityCalculator->getOrderIdsWithoutPickabilities();
    }
}
