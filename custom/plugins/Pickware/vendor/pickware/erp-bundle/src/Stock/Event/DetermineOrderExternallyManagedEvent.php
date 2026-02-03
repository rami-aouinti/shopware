<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\Event;

use InvalidArgumentException;
use Pickware\PhpStandardLibrary\Collection\Map;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * This event is dispatched when the InternalReservedStockUpdater needs to determine
 * which orders are externally managed (e.g., by Shopify or other integrations).
 * If an order is externally managed, its reserved stock should not be calculated
 * by the InternalReservedStockUpdater.
 */
class DetermineOrderExternallyManagedEvent extends Event
{
    /** @var Map<string, bool> $externallyManagedMap */
    private Map $externallyManagedMap;

    /**
     * @param list<string> $orderIds
     */
    public function __construct(array $orderIds)
    {
        $this->externallyManagedMap = Map::createWithDefault($orderIds, false);
    }

    /**
     * @return list<string>
     */
    public function getOrderIds(): array
    {
        return $this->externallyManagedMap->getKeys();
    }

    /**
     * Mark multiple orders as externally managed.
     *
     * @param list<string> $orderIds
     * @throws InvalidArgumentException if any order ID was not in the initial list
     */
    public function markOrdersAsExternallyManaged(array $orderIds): void
    {
        $knownOrderIds = $this->externallyManagedMap->getKeys();
        $unknownOrderIds = array_diff($orderIds, $knownOrderIds);

        if (count($unknownOrderIds) > 0) {
            throw new InvalidArgumentException(sprintf(
                'Cannot mark unknown order IDs as externally managed: %s',
                implode(', ', $unknownOrderIds),
            ));
        }

        $this->externallyManagedMap->merge(
            Map::createWithDefault($orderIds, true),
            fn(bool $oldValue, bool $newValue): bool => $oldValue || $newValue,
        );
    }

    /**
     * Check if all orders are externally managed.
     */
    public function areAllOrdersExternallyManaged(): bool
    {
        return array_reduce(
            $this->externallyManagedMap->getValues(),
            fn(bool $carry, bool $value): bool => $carry && $value,
            true,
        );
    }
}
