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

use InvalidArgumentException;
use Pickware\PhpStandardLibrary\Collection\CountingMap;
use Shopware\Core\Framework\Struct\Collection;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @extends Collection<OrderLineItemQuantity>
 * @deprecated Will be removed with 5.0.0. Use {@link CountingMap} instead.
 */
#[Exclude]
class OrderLineItemQuantityCollection extends Collection
{
    /**
     * @param iterable<OrderLineItemQuantity> $elements
     */
    public function __construct(iterable $elements = [])
    {
        parent::__construct(
            array_combine(
                array_map(
                    fn(OrderLineItemQuantity $orderLineItemQuantity) => $orderLineItemQuantity->getOrderLineItemId(),
                    $elements,
                ),
                $elements,
            ),
        );
    }

    protected function getExpectedClass(): string
    {
        return OrderLineItemQuantity::class;
    }

    public function addQuantity(string $orderLineItemId, int $quantity): void
    {
        if (!$this->has($orderLineItemId)) {
            throw new InvalidArgumentException(sprintf(
                'Order line item with id "%s" not found in collection.',
                $orderLineItemId,
            ));
        }

        $this->get($orderLineItemId)->increaseQuantity($quantity);
    }

    public function addQuantities(OrderLineItemQuantityCollection $other): void
    {
        foreach ($other as $orderLineItemQuantity) {
            $this->addQuantity($orderLineItemQuantity->getOrderLineItemId(), $orderLineItemQuantity->getQuantity());
        }
    }

    public function subtractQuantity(string $orderLineItemId, int $quantity): void
    {
        if (!$this->has($orderLineItemId)) {
            throw new InvalidArgumentException(sprintf(
                'Order line item with id "%s" not found in collection.',
                $orderLineItemId,
            ));
        }

        $this->get($orderLineItemId)->decreaseQuantity($quantity);
    }

    public function subtractQuantities(OrderLineItemQuantityCollection $other): void
    {
        foreach ($other as $orderLineItemQuantity) {
            $this->subtractQuantity($orderLineItemQuantity->getOrderLineItemId(), $orderLineItemQuantity->getQuantity());
        }
    }

    public function addOrIncrementQuantity(string $orderLineItemId, int $quantity): void
    {
        if ($this->has($orderLineItemId)) {
            $this->addQuantity($orderLineItemId, $quantity);
        } else {
            $this->set($orderLineItemId, new OrderLineItemQuantity($orderLineItemId, $quantity));
        }
    }

    public function contains(OrderLineItemQuantityCollection $other): bool
    {
        foreach ($other as $orderLineItemQuantity) {
            if (!$this->has($orderLineItemQuantity->getOrderLineItemId())) {
                return false;
            }

            if ($this->get($orderLineItemQuantity->getOrderLineItemId())->getQuantity() < $orderLineItemQuantity->getQuantity()) {
                return false;
            }
        }

        return true;
    }
}
