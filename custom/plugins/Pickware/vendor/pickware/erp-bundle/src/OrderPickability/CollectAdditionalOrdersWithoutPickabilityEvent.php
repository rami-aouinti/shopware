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

class CollectAdditionalOrdersWithoutPickabilityEvent
{
    /**
     * @var string[]
     */
    private array $ordersWithoutPickability = [];

    public function __construct() {}

    /**
     * @param string[] $orderIds
     */
    public function addOrdersWithoutPickability(array $orderIds): void
    {
        $this->ordersWithoutPickability = array_merge($this->ordersWithoutPickability, $orderIds);
    }

    /**
     * @return string[]
     */
    public function getOrdersWithoutPickability(): array
    {
        return $this->ordersWithoutPickability;
    }
}
