<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\AccountingDocument\AccountingDocumentRequest;

use Pickware\PickwareErpStarter\OrderCalculation\CalculatableOrder;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class AccountingDocumentRequestCalculationContext
{
    /**
     * @param bool $isPreDiscountFix Whether the order was created before the fix for broken discounts from shopify was applied
     * @see https://github.com/pickware/shopware-plugins/pull/5575
     * @param ?array<string> $orderVatIds
     */
    public function __construct(
        private readonly string $orderId,
        private readonly string $orderNumber,
        private readonly CalculatableOrder $calculatableOrder,
        private readonly bool $isShopifyOrder,
        private readonly bool $isPreDiscountFix,
        private readonly ?string $countryIsoCode,
        private readonly string $paymentMethodId,
        private readonly ?array $orderVatIds,
        private readonly ?string $orderCompany,
    ) {}

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function getCalculatableOrder(): CalculatableOrder
    {
        return $this->calculatableOrder;
    }

    public function isShopifyOrder(): bool
    {
        return $this->isShopifyOrder;
    }

    public function isPreDiscountFix(): bool
    {
        return $this->isPreDiscountFix;
    }

    public function getCountryIsoCode(): ?string
    {
        return $this->countryIsoCode;
    }

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }

    /**
     * @return ?array<string>
     */
    public function getOrderVatIds(): ?array
    {
        return $this->orderVatIds;
    }

    public function getOrderCompany(): ?string
    {
        return $this->orderCompany;
    }
}
