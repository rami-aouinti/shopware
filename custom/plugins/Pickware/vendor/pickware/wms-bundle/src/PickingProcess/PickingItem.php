<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProcess;

use Pickware\PhpStandardLibrary\Collection\CountingMap;
use Pickware\PickwareErpStarter\PickingProperty\PickingPropertyRecordValue;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Pickware\ShopwareExtensionsBundle\Context\ContextExtension;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
readonly class PickingItem
{
    /**
     * @param positive-int $quantity
     * @param PickingPropertyRecordValue[][] $pickingPropertyRecords
     */
    public function __construct(
        private string $stockMovementId,
        private StockLocationReference $source,
        private string $productId,
        private ?string $batchId,
        private int $quantity,
        private array $pickingPropertyRecords,
    ) {}

    public function getSource(): StockLocationReference
    {
        return $this->source;
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
     * @return PickingPropertyRecordValue[][]
     */
    public function getPickingPropertyRecords(): array
    {
        return $this->pickingPropertyRecords;
    }

    public function createStockMovementForDestination(
        StockLocationReference $destination,
        Context $context,
    ): StockMovement {
        $stockMovementPayload = [
            'id' => $this->stockMovementId,
            'productId' => $this->productId,
            'quantity' => $this->quantity,
            'source' => $this->source,
            'destination' => $destination,
            'userId' => ContextExtension::getUserId($context),
        ];
        if ($this->batchId) {
            $stockMovementPayload['batches'] = new CountingMap([
                $this->batchId => $this->quantity,
            ]);
        }

        return StockMovement::create($stockMovementPayload);
    }
}
