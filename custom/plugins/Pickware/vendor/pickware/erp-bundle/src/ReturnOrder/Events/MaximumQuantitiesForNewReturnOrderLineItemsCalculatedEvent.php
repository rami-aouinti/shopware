<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ReturnOrder\Events;

use Shopware\Core\Framework\Context;

class MaximumQuantitiesForNewReturnOrderLineItemsCalculatedEvent
{
    /**
     * @param array<string, <array<string, int>> $maximumQuantities array (map) for each order: maximum quantity for a return order line item referencing an order line item:
     *  [
     *    'order-id-1' => [
     *      'order-line-item-id-1' => 5,
     *      'order-line-item-id-2' => 0,
     *    ],
     *  ]
     */
    public function __construct(
        private array $maximumQuantities,
        private readonly Context $context,
    ) {}

    public function getMaximumQuantities(): array
    {
        return $this->maximumQuantities;
    }

    public function setMaximumQuantities(array $maximumQuantities): void
    {
        $this->maximumQuantities = $maximumQuantities;
    }

    public function getOrderIds(): array
    {
        return array_keys($this->maximumQuantities);
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
