<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Config;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Contracts\EventDispatcher\Event;

class OrderNumberPrefixCollectionEvent extends Event implements ShopwareEvent
{
    /**
     * @var string[]
     */
    private array $orderNumberPrefixes = [];

    public function __construct(
        private readonly Context $context,
    ) {}

    /**
     * @return string[]
     */
    public function getOrderNumberPrefixes(): array
    {
        return $this->orderNumberPrefixes;
    }

    /**
     * @param string[] $newOrderNumberPrefixes
     */
    public function appendOrderNumberPrefixes(array $newOrderNumberPrefixes): void
    {
        $this->orderNumberPrefixes = array_merge($this->orderNumberPrefixes, $newOrderNumberPrefixes);
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
