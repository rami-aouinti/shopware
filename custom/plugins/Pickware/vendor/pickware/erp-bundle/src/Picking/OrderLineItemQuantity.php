<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picking;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
/**
 * @deprecated Will be removed with 2.0.0. Use {@link CountingMap} instead.
 */
class OrderLineItemQuantity
{
    public function __construct(
        private readonly string $orderLineItemId,
        private int $quantity,
    ) {}

    public function getOrderLineItemId(): string
    {
        return $this->orderLineItemId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function increaseQuantity(int $quantity): void
    {
        $this->quantity += $quantity;
    }

    public function decreaseQuantity(int $quantity): void
    {
        $this->quantity -= $quantity;
    }
}
