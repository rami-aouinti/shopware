<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocking;

use InvalidArgumentException;
use JsonSerializable;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use function Pickware\PhpStandardLibrary\Optional\doIf;
use Pickware\PickwareErpStarter\Batch\BatchQuantity;
use Pickware\PickwareErpStarter\Batch\ImmutableBatchQuantityMap;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @phpstan-type ProductQuantityData = array{
 *     productId: string,
 *     quantity: int,
 *     batches?: array<string, int>,
 * }
 */
#[Exclude]
readonly class ProductQuantity implements JsonSerializable
{
    public function __construct(
        private string $productId,
        private int $quantity,
        private ?ImmutableBatchQuantityMap $batches = null,
    ) {
        if ($this->batches !== null && $this->batches->getTotal() > $this->quantity) {
            throw new InvalidArgumentException('The sum of the batch quantities cannot be greater than the quantity of the product.');
        }
    }

    /**
     * @param ProductQuantityData $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            productId: $array['productId'],
            quantity: $array['quantity'],
            batches: doIf($array['batches'], fn(array $batches) => new ImmutableBatchQuantityMap($batches)),
        );
    }

    /**
     * @return ProductQuantityData
     */
    public function jsonSerialize(): array
    {
        $payload = [
            'productId' => $this->productId,
            'quantity' => $this->quantity,
        ];
        if ($this->batches) {
            $payload['batches'] = $this->batches->jsonSerialize();
        }

        return $payload;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getBatches(): ?ImmutableBatchQuantityMap
    {
        return $this->batches;
    }

    /**
     * @return ImmutableCollection<BatchQuantity>
     */
    public function asBatchQuantities(): ImmutableCollection
    {
        if ($this->batches !== null) {
            $items = [];
            foreach ($this->batches as $batchId => $quantity) {
                $items[] = new BatchQuantity($this->productId, $batchId, $quantity);
            }
            if ($this->batches->getTotal() < $this->quantity) {
                $items[] = new BatchQuantity(
                    productId: $this->productId,
                    batchId: null,
                    quantity: $this->quantity - $this->batches->getTotal(),
                );
            }

            return new ImmutableCollection($items);
        }

        return new ImmutableCollection([
            new BatchQuantity($this->productId, null, $this->quantity),
        ]);
    }

    public function add(self $other): self
    {
        if ($this->productId !== $other->productId) {
            throw new InvalidArgumentException('Cannot add product quantities with different product IDs.');
        }
        if ($this->batches) {
            $combinedBatches = $this->batches->add($other->batches ?? new ImmutableBatchQuantityMap());
        } else {
            $combinedBatches = $other->batches;
        }

        return new ProductQuantity(
            productId: $this->productId,
            quantity: $this->quantity + $other->quantity,
            batches: $combinedBatches,
        );
    }

    public function negate(): self
    {
        return new ProductQuantity($this->productId, -1 * $this->quantity, $this->batches?->negate());
    }
}
