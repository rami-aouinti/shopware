<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Product;

use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;

class PickwareProductInsertedEvent
{
    /** @var list<string> $productIds  */
    private array $productIds;

    private Context $context;

    /**
     * @param list<string> $productIds
     */
    public function __construct(array $productIds)
    {
        $this->productIds = $productIds;
        $this->context = new Context(new SystemSource());
    }

    /**
     * @return list<string>
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
