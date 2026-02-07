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

use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @deprecated Only exists for backwards compatibility with pickware-wms. Will be removed in v5.0.0.
 */
#[Exclude]
class PickLocation
{
    public function __construct(
        readonly private StockLocationReference $stockLocationReference,
        readonly private int $quantityToPick,
    ) {}

    /**
     * @deprecated Will be removed in v5.0.0. Use {@link PickingRequest::getProductsToPick}` instead.
     */
    public function getStockLocationReference(): StockLocationReference
    {
        return $this->stockLocationReference;
    }

    /**
     * @deprecated Will be removed in v5.0.0. Use {@link PickingRequest::getProductsToPick}` instead.
     */
    public function getQuantityToPick(): int
    {
        return $this->quantityToPick;
    }
}
