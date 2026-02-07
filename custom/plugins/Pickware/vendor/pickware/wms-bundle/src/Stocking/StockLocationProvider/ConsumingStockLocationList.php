<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Stocking\StockLocationProvider;

use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\Stocking\StockLocationProvider\StockLocationProvider;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class ConsumingStockLocationList implements StockLocationProvider
{
    private array $prioritizedStockLocationReferences;

    /**
     * @param ImmutableCollection<StockLocationReference> $prioritizedStockLocationReferences
     */
    public function __construct(ImmutableCollection $prioritizedStockLocationReferences)
    {
        $this->prioritizedStockLocationReferences = $prioritizedStockLocationReferences->asArray();
    }

    public function getNextStockLocationForProduct(string $productId): ?StockLocationReference
    {
        return array_shift($this->prioritizedStockLocationReferences);
    }
}
