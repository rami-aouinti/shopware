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

use Pickware\DatevBundle\Config\AccountAssignment\Item\DebtorAccountRequestItem;
use Pickware\DatevBundle\Config\AccountAssignment\Item\RevenueAccountRequestItem;
use Pickware\DatevBundle\Config\AccountAssignment\TaxStatus;
use Pickware\DatevBundle\CostCenters\CostCentersService;
use Pickware\DatevBundle\PosAccountingDocument\PosAccountingDocumentClassifier;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Framework\Context;

class AccountingDocumentRequestFactory
{
    public function __construct(
        private readonly CostCentersService $costCentersService,
        private readonly AccountingDocumentPriceItemCollectionFactory $priceItemCollectionFactory,
        private readonly PosAccountingDocumentClassifier $posAccountingDocumentOrderDocumentClassifier,
        private readonly AccountingDocumentRequestCalculationContextFactory $accountingDocumentRequestCalculationContextFactory,
    ) {}

    public function createAccountingDocumentRequestForDocument(string $documentId, Context $context): AccountingDocumentRequest
    {
        if (!$this->posAccountingDocumentOrderDocumentClassifier->canDocumentBeExportedByDefaultExport($documentId, $context)) {
            return new AccountingDocumentRequest(ImmutableCollection::create());
        }

        $accountingDocumentRequestCalculationContext = $this->accountingDocumentRequestCalculationContextFactory
            ->createCalculationContext($documentId, $context);

        if ($accountingDocumentRequestCalculationContext->isShopifyOrder() && $accountingDocumentRequestCalculationContext->isPreDiscountFix()) {
            $priceItemCollectionForOrderPrice = $this->priceItemCollectionFactory->createPriceItemCollectionForBrokenShopifyOrder(
                $accountingDocumentRequestCalculationContext->getCalculatableOrder()->price,
            );
        } elseif ($accountingDocumentRequestCalculationContext->isShopifyOrder()) {
            // The tax status derived from the Shopify integration may be wrong, since at order import / invoice
            // creation time Shopify supplied surplus tax lines that are inconsistent with themselves (> 0 tax rate,
            // > 0 taxed price, 0 tax). Thus, recalculate the order tax status using the actual paid taxes.
            if ($accountingDocumentRequestCalculationContext->getCalculatableOrder()->price->getCalculatedTaxes()->getAmount() === 0.0) {
                $orderPrice = $accountingDocumentRequestCalculationContext->getCalculatableOrder()->price;
                $accountingDocumentRequestCalculationContext->getCalculatableOrder()->price = new CartPrice(
                    netPrice: $orderPrice->getNetPrice(),
                    totalPrice: $orderPrice->getTotalPrice(),
                    positionPrice: $orderPrice->getPositionPrice(),
                    calculatedTaxes: $orderPrice->getCalculatedTaxes(),
                    taxRules: $orderPrice->getTaxRules(),
                    taxStatus: CartPrice::TAX_STATE_FREE,
                    rawTotal: $orderPrice->getRawTotal(),
                );
            }

            $priceItemCollectionForOrderPrice = $this->priceItemCollectionFactory->createPriceItemCollectionForBrokenShopifyCalculatedTaxesUsingTwoSteps(
                $accountingDocumentRequestCalculationContext->getCalculatableOrder(),
            );
        } else {
            $priceItemCollectionForOrderPrice = $this->priceItemCollectionFactory->createPriceItemCollection(
                $accountingDocumentRequestCalculationContext->getCalculatableOrder()->price,
            );
        }

        $priceItemCollectionForOrderPrice = $this->costCentersService->addCostCentersToPriceItems(
            $priceItemCollectionForOrderPrice,
            $accountingDocumentRequestCalculationContext,
            $context,
        );

        return new AccountingDocumentRequest(
            ImmutableCollection::create(array_map(
                fn(AccountingDocumentPriceItem $priceItem) => new AccountingDocumentRequestItem(
                    price: $priceItem,
                    accountRequestItem: new RevenueAccountRequestItem(
                        key: sprintf(
                            '%s.%s',
                            $accountingDocumentRequestCalculationContext->getOrderId(),
                            $priceItem->getTaxRate(),
                        ),
                        orderNumber: $accountingDocumentRequestCalculationContext->getOrderNumber(),
                        taxStatus: TaxStatus::fromShopwareTaxStatus(
                            $accountingDocumentRequestCalculationContext->getCalculatableOrder()->price->getTaxStatus(),
                        ),
                        taxRate: $priceItem->getTaxRate(),
                        countryIsoCode: $accountingDocumentRequestCalculationContext->getCountryIsoCode(),
                        customerVatIds: $accountingDocumentRequestCalculationContext->getOrderVatIds(),
                        billedCompany: $accountingDocumentRequestCalculationContext->getOrderCompany(),
                    ),
                    contraAccountRequestItem: new DebtorAccountRequestItem(
                        key: $accountingDocumentRequestCalculationContext->getOrderId(),
                        paymentMethodId: $accountingDocumentRequestCalculationContext->getPaymentMethodId(),
                    ),
                ),
                $priceItemCollectionForOrderPrice->asArray(),
            )),
        );
    }
}
