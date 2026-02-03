<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Batch\OrderDocument;

/**
 * Immutable map of productId => OrderDocumentBatchInfoCollection
 */
readonly class ProductBatchInfoMap
{
    /**
     * @param array<string, OrderDocumentBatchInfoCollection> $batchInfoByProduct
     */
    public function __construct(
        private array $batchInfoByProduct = [],
    ) {}

    public function get(string $productId): OrderDocumentBatchInfoCollection
    {
        return $this->batchInfoByProduct[$productId] ?? OrderDocumentBatchInfoCollection::create();
    }

    public function isEmpty(): bool
    {
        return count($this->batchInfoByProduct) === 0;
    }

    /**
     * Validates that batch quantities match expected quantities for each product.
     *
     * @param array<string, int> $expectedQuantitiesByProduct productId => expectedQuantity
     */
    public function hasQuantityMismatch(array $expectedQuantitiesByProduct): bool
    {
        foreach ($expectedQuantitiesByProduct as $productId => $expectedQuantity) {
            $batchInfo = $this->get($productId);
            if ($batchInfo->isEmpty()) {
                continue;
            }
            if ($batchInfo->getTotalQuantity() !== $expectedQuantity) {
                return true;
            }
        }

        return false;
    }

    public function merge(self $other): self
    {
        $merged = $this->batchInfoByProduct;
        foreach ($other->batchInfoByProduct as $productId => $collection) {
            if (isset($merged[$productId])) {
                $merged[$productId] = $merged[$productId]->merge($collection);
            } else {
                $merged[$productId] = $collection;
            }
        }

        return new self($merged);
    }
}
