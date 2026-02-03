<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderShipping;

use InvalidArgumentException;
use JsonSerializable;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use function Pickware\PhpStandardLibrary\Optional\doIf;
use Pickware\PickwareErpStarter\Batch\BatchQuantityLocation;
use Pickware\PickwareErpStarter\Batch\ImmutableBatchQuantityMap;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @phpstan-import-type StockLocationReferenceData from StockLocationReference
 * @phpstan-type ProductQuantityLocationData = array{
 *     stockLocation: StockLocationReferenceData,
 *     productId: string,
 *     quantity: int,
 *     batches?: array<string, int>,
 * }
 */
#[Exclude]
readonly class ProductQuantityLocation implements JsonSerializable
{
    public function __construct(
        private StockLocationReference $locationReference,
        private string $productId,
        private int $quantity,
        private ?ImmutableBatchQuantityMap $batches = null,
    ) {
        if ($this->batches !== null && $this->batches->getTotal() > $this->quantity) {
            throw new InvalidArgumentException('The sum of the batch quantities cannot be greater than the quantity of the product.');
        }
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getStockLocationReference(): StockLocationReference
    {
        return $this->locationReference;
    }

    public function getBatches(): ?ImmutableBatchQuantityMap
    {
        return $this->batches;
    }

    /**
     * @return ImmutableCollection<BatchQuantityLocation>
     */
    public function asBatchQuantityLocations(): ImmutableCollection
    {
        if ($this->batches !== null) {
            $items = [];
            foreach ($this->batches as $batchId => $quantity) {
                $items[] = new BatchQuantityLocation($this->locationReference, $this->productId, $batchId, $quantity);
            }
            if ($this->batches->getTotal() !== $this->quantity) {
                $items[] = new BatchQuantityLocation(
                    location: $this->locationReference,
                    productId: $this->productId,
                    batchId: null,
                    quantity: $this->quantity - $this->batches->getTotal(),
                );
            }

            return new ImmutableCollection($items);
        }

        return new ImmutableCollection([
            new BatchQuantityLocation($this->locationReference, $this->productId, null, $this->quantity),
        ]);
    }

    /**
     * @return ProductQuantityLocationData
     */
    public function jsonSerialize(): array
    {
        $payload = [
            'productId' => $this->productId,
            'quantity' => $this->quantity,
            'stockLocation' => $this->locationReference->jsonSerialize(),
        ];
        if ($this->batches) {
            $payload['batches'] = $this->batches->jsonSerialize();
        }

        return $payload;
    }

    /**
     * @param ProductQuantityLocationData $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            locationReference: StockLocationReference::create($array['stockLocation']),
            productId: $array['productId'],
            quantity: $array['quantity'],
            batches: doIf($array['batches'], fn(array $batches) => new ImmutableBatchQuantityMap($batches)),
        );
    }
}
