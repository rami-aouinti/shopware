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

use Pickware\MoneyBundle\Currency;
use Pickware\MoneyBundle\CurrencyConverter;
use Pickware\MoneyBundle\MoneyValue;
use Pickware\ShippingBundle\ParcelHydration\CustomsInformationCustomFieldSet;
use Pickware\ShippingBundle\ParcelHydration\OrderLineItemParcelMapping;
use Shopware\Core\Content\Product\ProductEntity;

/**
 * This processor sets a fallback unit price for parcel items used as the customs value,
 * for line items whose unit price is effectively zero. We treat any price below 0.001 as zero
 * (since the smallest currency precision is three decimal places) to avoid floating point inaccuracies.
 *
 * Fallback logic:
 * 1. Use the fallback customs value from the product custom field, if available and > 0.
 * 2. If not available, use the product's net price (if the order is tax-free).
 * 3. Otherwise, use the product's gross price.
 */
class SetCustomsValueFallbackProcessor implements ParcelItemsProcessor
{
    public function __construct(
        private readonly CurrencyConverter $currencyConverter,
    ) {}

    /**
     * @param OrderLineItemParcelMapping[] $items
     * @return OrderLineItemParcelMapping[]
     */
    public function process(array $items, ProcessorContext $processorContext): array
    {
        $commonShippingConfig = $processorContext->getCommonShippingConfig();
        $useCustomsValuePriority = $commonShippingConfig->prioritizeProductCustomsValueForParcelItemCustomsValue();

        foreach ($items as $item) {
            $orderLineItem = $item->getOrderLineItem();
            $parcelItem = $item->getParcelItem();

            if (!$parcelItem) {
                continue;
            }

            $product = $orderLineItem->getProduct();
            if (!$product) {
                continue;
            }

            $customsValueFallback = $this->getFallbackValueInOrderCurrency(
                $product,
                $processorContext,
            );

            $unitPriceIsZero = ($parcelItem->getUnitPrice()?->getValue() ?? 0.0) < 0.001;

            if ($customsValueFallback && ($useCustomsValuePriority || $unitPriceIsZero)) {
                $parcelItem->setUnitPrice($customsValueFallback);

                continue;
            }

            if (!$unitPriceIsZero) {
                continue;
            }

            if ($processorContext->isOrderTaxFree()) {
                $unitPrice = $product->getPrice()?->getCurrencyPrice($processorContext->getOrderCurrency()->getId(), false)?->getNet() ?? 0.0;
            } else {
                $unitPrice = $product->getPrice()?->getCurrencyPrice($processorContext->getOrderCurrency()->getId(), false)?->getGross() ?? 0.0;
            }

            $parcelItem->setUnitPrice(
                new MoneyValue(
                    $unitPrice,
                    new Currency($processorContext->getOrderCurrency()->getIsoCode()),
                ),
            );
        }

        return $items;
    }

    private function getFallbackValueInOrderCurrency(
        ProductEntity $product,
        ProcessorContext $processorContext,
    ): ?MoneyValue {
        $customFields = $product->getTranslation('customFields');
        $customsValueFieldName = CustomsInformationCustomFieldSet::CUSTOM_FIELD_NAME_CUSTOMS_INFORMATION_CUSTOMS_VALUE;
        $customsValueFallback = $customFields[$customsValueFieldName] ?? null;

        if ($customsValueFallback === null) {
            return null;
        }

        if ($customsValueFallback < 0.001) {
            return null;
        }

        $fallbackValue = new MoneyValue(
            $customsValueFallback,
            new Currency($processorContext->getDefaultCurrency()->getIsoCode()),
        );

        if ($processorContext->getDefaultCurrency()->getIsoCode() !== $processorContext->getOrderCurrency()->getIsoCode()) {
            $fallbackValue = $this->currencyConverter->convertMoneyValueToCurrency(
                $fallbackValue,
                new Currency($processorContext->getOrderCurrency()->getIsoCode()),
                $processorContext->getShopwareContext(),
            );
        }

        return $fallbackValue;
    }
}
