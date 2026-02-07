<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt;

use Pickware\PhpStandardLibrary\Collection\CountingMap;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Pickware\ShopwareExtensionsBundle\Context\ContextExtension;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
readonly class GoodsReceiptStockItem
{
    public function __construct(
        private string $productId,
        private ?string $batchId,
        private int $quantity,
        private ?string $orderId,
    ) {}

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getBatchId(): ?string
    {
        return $this->batchId;
    }

    public function createStockMovement(
        StockLocationReference $source,
        StockLocationReference $destination,
        Context $context,
    ): StockMovement {
        return $this->createStockMovementWithQuantity($source, $destination, $this->quantity, $context);
    }

    public function createStockMovementWithQuantity(
        StockLocationReference $source,
        StockLocationReference $destination,
        int $quantity,
        Context $context,
    ): StockMovement {
        $stockMovementPayload = [
            'productId' => $this->productId,
            'quantity' => $quantity,
            'source' => $source,
            'destination' => $destination,
        ];
        if (ContextExtension::hasUser($context)) {
            $stockMovementPayload['userId'] = ContextExtension::getUserId($context);
        }
        if ($this->batchId) {
            $stockMovementPayload['batches'] = new CountingMap([
                $this->batchId => $quantity,
            ]);
        }

        return StockMovement::create($stockMovementPayload);
    }
}
