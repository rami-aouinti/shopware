<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\ParcelHydration\Processor;

use Pickware\ShippingBundle\Config\CommonShippingConfig;
use Pickware\ShippingBundle\ParcelHydration\ParcelHydrationConfiguration;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Currency\CurrencyEntity;

readonly class ProcessorContext
{
    public function __construct(
        private string $orderNumber,
        private bool $isOrderTaxFree,
        private CurrencyEntity $orderCurrency,
        private CurrencyEntity $defaultCurrency,
        private ParcelHydrationConfiguration $config,
        private CommonShippingConfig $commonShippingConfig,
        private Context $shopwareContext,
    ) {}

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function isOrderTaxFree(): bool
    {
        return $this->isOrderTaxFree;
    }

    public function getOrderCurrency(): CurrencyEntity
    {
        return $this->orderCurrency;
    }

    public function getDefaultCurrency(): CurrencyEntity
    {
        return $this->defaultCurrency;
    }

    public function getConfig(): ParcelHydrationConfiguration
    {
        return $this->config;
    }

    public function getCommonShippingConfig(): CommonShippingConfig
    {
        return $this->commonShippingConfig;
    }

    public function getShopwareContext(): Context
    {
        return $this->shopwareContext;
    }
}
