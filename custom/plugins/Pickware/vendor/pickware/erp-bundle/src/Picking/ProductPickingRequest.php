<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picking;

use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @deprecated Only exists for backwards compatibility with pickware-wms. Will be removed in v5.0.0.
 */
#[Exclude]
class ProductPickingRequest
{
    private ProductQuantity $productQuantity;

    public function __construct(
        string $productId,
        int $quantity,
        readonly private array $pickLocations = [],
    ) {
        $this->productQuantity = new ProductQuantity(productId: $productId, quantity: $quantity);
    }

    /**
     * @deprecated Will be removed in v5.0.0. Use {@link PickingRequest::getProductsToPick}` instead.
     */
    public function getProductId(): string
    {
        return $this->productQuantity->getProductId();
    }

    /**
     * @deprecated Will be removed in v5.0.0. Use {@link PickingRequest::getProductsToPick}` instead.
     */
    public function getQuantity(): int
    {
        return $this->productQuantity->getQuantity();
    }

    /**
     * @deprecated Will be removed in v5.0.0. Use {@link PickingRequest::getProductsToPick}` instead.
     */
    public function getPickLocations(): array
    {
        return $this->pickLocations;
    }
}
