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

use Pickware\PhpStandardLibrary\Collection\Map;
use Shopware\Core\Framework\Context;

readonly class ProductStockNotAvailableForSaleCalculationEvent
{
    /**
     * @param list<string> $productIds
     * @param Map<string, int> $stockNotAvailableForSaleByProductId
     */
    public function __construct(
        private array $productIds,
        private Map $stockNotAvailableForSaleByProductId,
        private Context $context,
    ) {}

    /**
     * @param non-negative-int $stockNotAvailableForSale
     */
    public function addStockNotAvailableForSaleForProductId(string $productId, int $stockNotAvailableForSale): void
    {
        $this->stockNotAvailableForSaleByProductId->mergeEntry($productId, $stockNotAvailableForSale, fn(int $oldValue, int $newValue): int => $oldValue + $newValue);
    }

    /**
     * @return list<string>
     */
    public function getProductIds(): array
    {
        return $this->productIds;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
