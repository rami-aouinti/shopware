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

use DateTime;
use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\PickwareDatevBundle;
use Pickware\PickwareErpStarter\InvoiceCorrection\InvoiceCorrectionCalculator;
use Pickware\PickwareErpStarter\InvoiceCorrection\InvoiceCorrectionConfigGenerator;
use Pickware\PickwareErpStarter\InvoiceCorrection\InvoiceCorrectionDocumentType;
use Pickware\PickwareErpStarter\InvoiceCorrection\Model\PickwareDocumentVersionEntity;
use Pickware\PickwareErpStarter\OrderCalculation\CalculatableOrderFactory;
use Pickware\ShopwareExtensionsBundle\OrderTransaction\OrderTransactionCollectionExtension;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class AccountingDocumentRequestCalculationContextFactory
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly CalculatableOrderFactory $calculatableOrderFactory,
        private readonly InvoiceCorrectionCalculator $invoiceCorrectionCalculator,
        private readonly AccountingDocumentRequestAddressService $accountingDocumentRequestAddressService,
    ) {}

    public function createCalculationContext(string $documentId, Context $context): AccountingDocumentRequestCalculationContext
    {
        /** @var DocumentEntity $document */
        $document = $this->entityManager->getByPrimaryKey(
            DocumentDefinition::class,
            $documentId,
            $context,
            [
                'documentType',
                'pickwareErpDocumentVersion',
            ],
        );

        /** @var PickwareDocumentVersionEntity|null $pickwareDocumentVersion */
        $pickwareDocumentVersion = $document->getExtension('pickwareErpDocumentVersion');

        /** @var OrderEntity $order */
        $order = $this->entityManager->getByPrimaryKey(
            OrderDefinition::class,
            $document->getOrderId(),
            $context->createWithVersionId($pickwareDocumentVersion?->getOrderVersionId() ?? $document->getOrderVersionId()),
            [
                'transactions.stateMachineState',
                'orderCustomer.customer',
                'salesChannel',
                'billingAddress',
            ],
        );
        $orderId = $order->getId();

        if ($document->getDocumentType()->getTechnicalName() === InvoiceCorrectionDocumentType::TECHNICAL_NAME) {
            $referencedDocumentId = $document->getConfig()['custom'][InvoiceCorrectionConfigGenerator::DOCUMENT_CONFIGURATION_REFERENCED_DOCUMENT_ID_KEY];

            $calculatableOrder = $this->invoiceCorrectionCalculator->calculateInvoiceCorrection(
                $document->getOrderId(),
                $referencedDocumentId,
                $pickwareDocumentVersion?->getOrderVersionId() ?? $document->getOrderVersionId(),
                $context,
            );
        } else {
            $calculatableOrder = $this->calculatableOrderFactory->createCalculatableOrderFromOrder(
                $orderId,
                $context->createWithVersionId($pickwareDocumentVersion?->getOrderVersionId() ?? $document->getOrderVersionId()),
            );
        }

        $isShopifyOrder = $order->getSalesChannel()->getTypeId() === PickwareDatevBundle::PICKWARE_SHOPIFY_INTEGRATION_SALES_CHANNEL_TYPE_ID;
        $countryIsoCode = $this->accountingDocumentRequestAddressService->getCountryIsoCodeForOrder(
            $orderId,
            $context->createWithVersionId($pickwareDocumentVersion?->getOrderVersionId() ?? $document->getOrderVersionId()),
        );
        $paymentMethodId = OrderTransactionCollectionExtension::getPrimaryOrderTransaction(
            $order->getTransactions(),
        )->getPaymentMethodId();

        return new AccountingDocumentRequestCalculationContext(
            orderId: $orderId,
            orderNumber: $order->getOrderNumber(),
            calculatableOrder: $calculatableOrder,
            isShopifyOrder: $isShopifyOrder,
            isPreDiscountFix: $order->getOrderDate() < new DateTime('2024-02-03'),
            countryIsoCode: $countryIsoCode,
            paymentMethodId: $paymentMethodId,
            orderVatIds: $order->getOrderCustomer()->getCustomer()?->getVatIds() ?? $order->getOrderCustomer()->getVatIds(),
            orderCompany: $order->getBillingAddress()->getCompany(),
        );
    }
}
