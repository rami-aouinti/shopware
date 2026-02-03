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

use Pickware\PhpStandardLibrary\Collection\Map;
use Pickware\PickwareErpStarter\OrderPickability\Model\OrderPickabilityCollection;
use Pickware\PickwareErpStarter\OrderPickability\Model\OrderPickabilityDefinition;

/**
 * @phpstan-import-type OrderPickabilityStatus from OrderPickabilityDefinition
 */
interface OrderPickabilityCalculatorInterface
{
    /**
     * @param string[] $orderIds
     */
    public function calculateOrderPickabilitiesForOrders(array $orderIds): OrderPickabilityCollection;

    /**
     * @param string[] $warehouseIds
     */
    public function calculateOrderPickabilitiesForWarehouses(array $warehouseIds): OrderPickabilityCollection;

    /**
     * @return Map<string, array{status: OrderPickabilityStatus, availableStock: int, requiredStock: int}>
     */
    public function calculateProductPickabilitiesForOrderAndWarehouse(string $orderId, string $warehouseId): Map;

    /**
     * @return string[]
     */
    public function getOrderIdsWithoutPickabilities(): array;
}
