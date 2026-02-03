<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\DeliveryNote;

use Pickware\PhpStandardLibrary\Collection\CountingMap;
use Pickware\PickwareErpStarter\Picking\OrderLineItemQuantity;
use Pickware\PickwareErpStarter\Picking\OrderLineItemQuantityCollection;
use Shopware\Core\Framework\Context;

class DeliveryNoteLineItemFilterEvent
{
    /**
     * @param CountingMap<string> $lineItemQuantities
     * @param array<string, mixed> $customFields
     */
    public function __construct(
        private readonly string $orderId,
        private CountingMap $lineItemQuantities,
        private array $customFields,
        private readonly Context $context,
    ) {}

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    /**
     * @deprecated Use getLineItemQuantities instead
     */
    public function getOrderLineItemQuantities(): OrderLineItemQuantityCollection
    {
        return new OrderLineItemQuantityCollection(
            $this->lineItemQuantities->mapToList(
                fn(string $orderLineItemId, int $quantity) => new OrderLineItemQuantity($orderLineItemId, $quantity),
            ),
        );
    }

    /**
     * @return CountingMap<string>
     */
    public function getLineItemQuantities(): CountingMap
    {
        return $this->lineItemQuantities;
    }

    /**
     * @param CountingMap<string> $lineItemQuantities
     */
    public function setOrderLineItemQuantities(OrderLineItemQuantityCollection|CountingMap $lineItemQuantities): void
    {
        if (!($lineItemQuantities instanceof CountingMap)) {
            trigger_error(
                sprintf(
                    'Passing an %s to %s::setOrderLineItemQuantities() ' .
                    'is deprecated and will be removed. Use a CountingMap instead.',
                    OrderLineItemQuantityCollection::class,
                    self::class,
                ),
                E_USER_DEPRECATED,
            );
            $lineItemQuantities = new CountingMap($lineItemQuantities->map(
                fn($orderLineItemQuantity) => $orderLineItemQuantity->getQuantity(),
            ));
        }
        $this->lineItemQuantities = $lineItemQuantities;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCustomFields(): array
    {
        return $this->customFields;
    }

    /**
     * @param array<string, mixed> $customFields
     */
    public function updateCustomFields(array $customFields): void
    {
        $this->customFields = array_replace($this->customFields, $customFields);
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
