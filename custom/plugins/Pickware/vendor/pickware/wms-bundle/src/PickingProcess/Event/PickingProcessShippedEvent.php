<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProcess\Event;

use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Shopware\Core\Framework\Context;

class PickingProcessShippedEvent
{
    private string $pickingProcessId;
    private bool $orderWasShippedCompletely;
    private Context $context;

    /**
     * @var ProductQuantity[]
     */
    private array $shippedProductQuantities;

    /**
     * @param ProductQuantity[] $shippedProductQuantities
     */
    public function __construct(
        string $pickingProcessId,
        array $shippedProductQuantities,
        bool $orderWasShippedCompletely,
        Context $context,
    ) {
        $this->pickingProcessId = $pickingProcessId;
        $this->shippedProductQuantities = $shippedProductQuantities;
        $this->orderWasShippedCompletely = $orderWasShippedCompletely;
        $this->context = $context;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getPickingProcessId(): string
    {
        return $this->pickingProcessId;
    }

    public function getOrderWasShippedCompletely(): bool
    {
        return $this->orderWasShippedCompletely;
    }

    /**
     * @return ProductQuantity[]
     */
    public function getShippedProductQuantities(): array
    {
        return $this->shippedProductQuantities;
    }
}
