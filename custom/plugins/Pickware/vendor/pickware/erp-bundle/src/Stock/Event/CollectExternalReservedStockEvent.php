<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\Event;

use Pickware\PhpStandardLibrary\Collection\CountingMap;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * This event is dispatched when the ExternalReservedStockUpdater needs to collect
 * external reserved stock from various sources (e.g., Shopify committed quantity).
 *
 * Event subscribers can add their external reservations to the CountingMap, which
 * will be written to the external_reserved_stock field.
 */
class CollectExternalReservedStockEvent extends Event
{
    /**
     * @param list<string> $productIds Product IDs for which to collect external reservations
     * @param CountingMap<string> $externalReservedStock Map of product ID => external reserved stock quantity
     */
    public function __construct(
        private readonly array $productIds,
        private readonly CountingMap $externalReservedStock,
        private readonly Context $context,
    ) {}

    /**
     * @return array<string>
     */
    public function getProductIds(): array
    {
        return $this->productIds;
    }

    /**
     * @return CountingMap<string>
     */
    public function getExternalReservedStock(): CountingMap
    {
        return $this->externalReservedStock;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
