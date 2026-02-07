<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\StockingProcess;

use Pickware\PhpStandardLibrary\Collection\CountingMap;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocation;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Pickware\ShopwareExtensionsBundle\Context\ContextExtension;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
readonly class StockingItem
{
    /**
     * @param positive-int $quantity
     * @param string|null $idempotencyKey if set, the first stock movement created from this item will have this key as the id
     */
    public function __construct(
        private string $stockingProcessId,
        private StockLocationReference $destination,
        private string $productId,
        private ?string $batchId,
        private int $quantity,
        private ?string $idempotencyKey = null,
    ) {}

    public function getStockingProcessId(): string
    {
        return $this->stockingProcessId;
    }

    public function getDestination(): StockLocationReference
    {
        return $this->destination;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * Creates stock movements for the given sources.
     * If this item was created with an idempotency key, the first stock movement will have this key as the id.
     *
     * @param ImmutableCollection<ProductQuantityLocation> $sources
     * @return StockMovement[]
     */
    public function createStockMovementsForSources(
        ImmutableCollection $sources,
        Context $context,
    ): array {
        $quantityToMove = $this->quantity;
        $stockMovementPayloads = [];
        foreach ($sources as $source) {
            $stockMovementQuantity = min($quantityToMove, $source->getQuantity());
            $stockMovementPayload = [
                'productId' => $this->productId,
                'quantity' => $stockMovementQuantity,
                'source' => $source->getStockLocationReference(),
                'destination' => $this->destination,
                'userId' => ContextExtension::getUserId($context),
            ];
            if ($this->batchId) {
                $stockMovementPayload['batches'] = new CountingMap([
                    $this->batchId => $stockMovementQuantity,
                ]);
            }
            $quantityToMove -= $stockMovementQuantity;
            $stockMovementPayloads[] = $stockMovementPayload;
        }

        if ($quantityToMove !== 0) {
            throw StockingProcessException::notEnoughStock($this->stockingProcessId);
        }

        if ($this->idempotencyKey) {
            $stockMovementPayloads[0]['id'] = $this->idempotencyKey;
        }

        return array_map(StockMovement::create(...), $stockMovementPayloads);
    }
}
