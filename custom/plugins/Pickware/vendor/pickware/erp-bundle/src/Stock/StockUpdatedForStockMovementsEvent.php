<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock;

use Shopware\Core\Framework\Context;

readonly class StockUpdatedForStockMovementsEvent
{
    public function __construct(private array $stockMovements, private Context $context) {}

    public function getStockMovements(): array
    {
        return $this->stockMovements;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
