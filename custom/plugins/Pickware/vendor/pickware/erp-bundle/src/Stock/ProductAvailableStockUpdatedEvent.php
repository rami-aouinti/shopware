<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock;

use Shopware\Core\Framework\Context;

readonly class ProductAvailableStockUpdatedEvent
{
    private Context $context;

    /**
     * @param string[] $productIds
     */
    public function __construct(private array $productIds, ?Context $context = null)
    {
        if ($context === null) {
            trigger_error('Parameter Context will be required in v5', E_USER_DEPRECATED);
            $this->context = Context::createCLIContext();
        } else {
            $this->context = $context;
        }
    }

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
