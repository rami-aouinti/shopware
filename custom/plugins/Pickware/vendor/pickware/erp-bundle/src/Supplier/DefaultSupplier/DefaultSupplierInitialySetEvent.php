<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Supplier\DefaultSupplier;

use Shopware\Core\Framework\Context;

/**
 * This event is dispatched when the first product supplier configuration is created for a product, subsequently setting
 * the configurations supplier as the default one for said product.
 * Note that when this event is emitted, @see DefaultSupplierUpdatedEvent is not emitted.
 */
class DefaultSupplierInitialySetEvent
{
    /**
     * @param string[] $productIds
     */
    public function __construct(
        private readonly array $productIds,
        private readonly Context $context,
    ) {}

    /**
     * @return string[]
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
