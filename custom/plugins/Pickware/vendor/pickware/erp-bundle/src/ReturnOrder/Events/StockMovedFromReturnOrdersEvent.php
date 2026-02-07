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

use InvalidArgumentException;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

class StockMovedFromReturnOrdersEvent extends Event
{
    public function __construct(
        private readonly ImmutableCollection $stockMovements,
        private readonly Context $context,
    ) {
        $sourceIsNotReturnOrder = fn(StockMovement $stockMovement) => !$stockMovement->getSource()->isReturnOrder();
        if ($this->stockMovements->containsElementSatisfying($sourceIsNotReturnOrder)) {
            throw new InvalidArgumentException('All stock movements must originate from return orders.');
        }
    }

    /**
     * @return ImmutableCollection<StockMovement>
     */
    public function getStockMovements(): ImmutableCollection
    {
        return $this->stockMovements;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return ImmutableCollection<string>
     */
    public function getReturnOrderIds(): ImmutableCollection
    {
        return $this->stockMovements->map(
            fn(StockMovement $stockMovement) => $stockMovement->getSource()->getReturnOrderId(),
        );
    }
}
