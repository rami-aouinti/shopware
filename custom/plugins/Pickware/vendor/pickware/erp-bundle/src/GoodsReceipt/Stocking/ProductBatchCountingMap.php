<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt\Stocking;

use InvalidArgumentException;
use LogicException;
use Pickware\PickwareErpStarter\Batch\ImmutableBatchQuantityMap;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockCollection;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;

class ProductBatchCountingMap
{
    private const NO_BATCH_KEY = 'NO_BATCH';

    /**
     * @var array<string, array<string, int>>
     */
    private array $map = [];

    public static function fromStockCollection(StockCollection $stocks): self
    {
        $map = new self();
        foreach ($stocks as $stock) {
            $batchCounts = $stock->getBatchMappings()->asBatchCountingMap();
            foreach ($batchCounts as $batchId => $quantity) {
                $map->add($stock->getProductId(), $batchId, $quantity);
            }
            $unbatchedStock = $stock->getQuantity() - $batchCounts->getTotalCount();
            $map->add($stock->getProductId(), null, $unbatchedStock);
        }

        return $map;
    }

    public function get(string $productId, ?string $batchId): int
    {
        return $this->map[$productId][$batchId ?? self::NO_BATCH_KEY] ?? 0;
    }

    public function add(string $productId, ?string $batchId, int $amount): void
    {
        $count = $this->get($productId, $batchId) + $amount;
        if ($count < 0) {
            throw new InvalidArgumentException(sprintf(
                'The count for product ID "%s" and batch ID "%s" cannot become negative (attempted: %d).',
                $productId,
                $batchId ?? 'null',
                $count,
            ));
        }
        if ($count === 0) {
            unset($this->map[$productId][$batchId ?? self::NO_BATCH_KEY]);
            if (empty($this->map[$productId])) {
                unset($this->map[$productId]);
            }

            return;
        }

        $this->map[$productId][$batchId ?? self::NO_BATCH_KEY] = $count;
    }

    public function isEmpty(): bool
    {
        return count($this->map) === 0;
    }

    public function toProductQuantityCollection(): ProductQuantityImmutableCollection
    {
        $productQuantities = [];
        foreach ($this->map as $productId => $batches) {
            $hasUnbatchedStock = array_key_exists(self::NO_BATCH_KEY, $batches);
            if ($hasUnbatchedStock && count($batches) > 1) {
                throw new LogicException('Batch information is incomplete for product with ID ' . $productId);
            }
            $productQuantities[] = new ProductQuantity(
                productId: $productId,
                quantity: array_sum($batches),
                batches: $hasUnbatchedStock ? null : new ImmutableBatchQuantityMap($batches),
            );
        }

        return ProductQuantityImmutableCollection::create($productQuantities);
    }
}
