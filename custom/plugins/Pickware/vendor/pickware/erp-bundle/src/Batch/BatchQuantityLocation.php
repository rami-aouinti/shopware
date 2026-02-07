<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Batch;

use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocation;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
readonly class BatchQuantityLocation
{
    public function __construct(
        private StockLocationReference $location,
        private string $productId,
        private ?string $batchId,
        private int $quantity,
    ) {}

    public function getLocation(): StockLocationReference
    {
        return $this->location;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getBatchId(): ?string
    {
        return $this->batchId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function asProductQuantityLocation(): ProductQuantityLocation
    {
        return new ProductQuantityLocation(
            locationReference: $this->location,
            productId: $this->productId,
            quantity: $this->quantity,
            batches: $this->batchId ? new ImmutableBatchQuantityMap([$this->batchId => $this->quantity]) : null,
        );
    }
}
