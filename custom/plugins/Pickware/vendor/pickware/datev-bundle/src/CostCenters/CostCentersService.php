<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\CostCenters;

use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\AccountingDocument\AccountingDocumentRequest\AccountingDocumentPriceItem;
use Pickware\DatevBundle\AccountingDocument\AccountingDocumentRequest\AccountingDocumentPriceItemCollection;
use Pickware\DatevBundle\AccountingDocument\AccountingDocumentRequest\AccountingDocumentRequestCalculationContext;
use Pickware\DatevBundle\Config\DatevProductInformationCustomFieldSet;
use Pickware\DatevBundle\Config\Values\ConfigValues;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\OrderCalculation\CalculatableOrderLineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;

class CostCentersService
{
    public function __construct(
        private readonly FeatureFlagService $featureFlagService,
        private readonly EntityManager $entityManager,
    ) {}

    public function getCostCenters(
        AccountingDocumentPriceItem $priceItem,
        ConfigValues $configValues,
        string $salesChannelName,
        string $documentType,
        string $documentNumber,
        string $orderNumber,
    ): MappedCostCenters {
        if (!$this->featureFlagService->isActive(CostCentersFeatureFlag::NAME)) {
            return new MappedCostCenters(
                costCenter1: null,
                costCenter2: null,
            );
        }

        $messages = [];
        $salesChannelCostCenter = $configValues->getCostCenters()->getSalesChannelCostCenter();
        $productCostCenter = $priceItem->getProductCostCenter()?->getCostCenter();

        if (!$this->isValidCostCenter($salesChannelCostCenter)) {
            $messages[] = CostCentersMessage::createSalesChannelCostCenterFormatNotValidMessage(
                $salesChannelCostCenter,
                $salesChannelName,
                $documentType,
                $documentNumber,
                $orderNumber,
            );
            $salesChannelCostCenter = null;
        }

        if (!$this->isValidCostCenter($productCostCenter)) {
            $messages[] = CostCentersMessage::createProductCostCenterFormatNotValidMessage(
                $productCostCenter,
                $priceItem->getProductCostCenter()?->getProductNumber(),
                $documentType,
                $documentNumber,
                $orderNumber,
            );
            $productCostCenter = null;
        }

        if ($configValues->getCostCenters()->getSwitchCostCentersOrder()) {
            return new MappedCostCenters(
                costCenter1: $productCostCenter,
                costCenter2: $salesChannelCostCenter,
                messages: $messages,
            );
        }

        return new MappedCostCenters(
            costCenter1: $salesChannelCostCenter,
            costCenter2: $productCostCenter,
            messages: $messages,
        );
    }

    private function isValidCostCenter(?string $costCenter): bool
    {
        if ($costCenter === null) {
            return true;
        }

        return preg_match('/^([\\w ]{0,36})$/', $costCenter) === 1;
    }

    public function addCostCentersToPriceItems(
        AccountingDocumentPriceItemCollection $priceItems,
        AccountingDocumentRequestCalculationContext $calculationContext,
        Context $context,
    ): AccountingDocumentPriceItemCollection {
        if (!$this->featureFlagService->isActive(CostCentersFeatureFlag::NAME)) {
            return $priceItems;
        }

        $order = $calculationContext->getCalculatableOrder();
        $lineItems = ImmutableCollection::create($order->lineItems);

        // Filter out line items which do not reference a product, as they can not have a product cost center
        // We do not however assert the line items type, as DATEV has to capture all possible cost center assignments
        $relevantLineItems = $lineItems->filter(
            fn(CalculatableOrderLineItem $lineItem) => $lineItem->productId !== null,
        );
        $productIds = $relevantLineItems->map(
            fn(CalculatableOrderLineItem $lineItem) => $lineItem->productId,
        );

        /** @var ProductCollection $products */
        $products = $context->enableInheritance(fn() => $this->entityManager->findBy(
            ProductDefinition::class,
            ['id' => $productIds->asArray()],
            $context,
        ));
        /** @var CalculatableOrderLineItem $lineItem */
        foreach ($relevantLineItems as $lineItem) {
            $costCenter = $this->getCostCenterFromLineItem($lineItem, $products);

            if ($costCenter === null) {
                continue;
            }

            if ($order->price->getTaxStatus() === CartPrice::TAX_STATE_FREE) {
                $price = $lineItem->price->getTotalPrice();
                $priceItems = $this->adjustPriceItemByCostCenterAndTaxRate(
                    priceItems: $priceItems,
                    costCenter: $costCenter,
                    price: $price,
                    taxRate: null,
                );

                $priceItems = $this->adjustPriceItemByCostCenterAndTaxRate(
                    priceItems: $priceItems,
                    costCenter: null,
                    price: -$price,
                    taxRate: null,
                );
            } else {
                foreach ($lineItem->price->getCalculatedTaxes() as $calculatedTax) {
                    $taxRate = $calculatedTax->getTaxRate();
                    $price = $calculatedTax->getPrice();
                    $priceItems = $this->adjustPriceItemByCostCenterAndTaxRate(
                        priceItems: $priceItems,
                        costCenter: $costCenter,
                        price: $price,
                        taxRate: $taxRate,
                    );

                    $priceItems = $this->adjustPriceItemByCostCenterAndTaxRate(
                        priceItems: $priceItems,
                        costCenter: null,
                        price: -$price,
                        taxRate: $taxRate,
                    );
                }
            }
        }

        return $priceItems->filterNonContributingPriceItems();
    }

    private function adjustPriceItemByCostCenterAndTaxRate(
        AccountingDocumentPriceItemCollection $priceItems,
        ?ProductCostCenter $costCenter,
        float $price,
        ?float $taxRate,
    ): AccountingDocumentPriceItemCollection {
        $existingPriceItem = $priceItems->first(fn(AccountingDocumentPriceItem $priceItem) =>
            $priceItem->getProductCostCenter()?->getCostCenter() === $costCenter?->getCostCenter()
            && $priceItem->getTaxRate() === $taxRate);

        if ($existingPriceItem !== null) {
            $existingPriceItem
                ->setPrice($existingPriceItem->getPrice() + $price);
        } else {
            $newPriceItem = new AccountingDocumentPriceItem(
                price: $price,
                taxRate: $taxRate,
                productCostCenter: $costCenter,
            );

            $priceItems = AccountingDocumentPriceItemCollection::fromArray(
                [
                    ...$priceItems->asArray(),
                    $newPriceItem,
                ],
                null,
            );
        }

        return $priceItems;
    }

    private function getCostCenterFromLineItem(
        CalculatableOrderLineItem $lineItem,
        ProductCollection $products,
    ): ?ProductCostCenter {
        $product = $products->get($lineItem->productId);

        if ($product === null) {
            return null;
        }

        $productNumber = $product->getProductNumber();

        $productCostCenter = $product->getCustomFields()[
            DatevProductInformationCustomFieldSet::CUSTOM_FIELD_NAME_PRODUCT_COST_CENTER
        ] ?? null;

        if ($productCostCenter === null) {
            return null;
        }

        return new ProductCostCenter(
            productNumber: $productNumber,
            costCenter: $productCostCenter,
        );
    }
}
