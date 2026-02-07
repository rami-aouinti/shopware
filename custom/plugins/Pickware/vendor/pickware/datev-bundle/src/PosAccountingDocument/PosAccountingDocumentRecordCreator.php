<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PosAccountingDocument;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use LogicException;
use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\AccountingDocument\AccountingDocumentMessage;
use Pickware\DatevBundle\AccountingDocument\AccountingDocumentRequest\AccountingDocumentPriceItem;
use Pickware\DatevBundle\AccountingDocument\AccountingDocumentRequest\AccountingDocumentPriceItemCollectionFactory;
use Pickware\DatevBundle\AccountingDocumentPicture\Guid\AccountingDocumentGuidService;
use Pickware\DatevBundle\CompanyCodes\CompanyCodeMessageMetadata;
use Pickware\DatevBundle\CompanyCodes\CompanyCodesService;
use Pickware\DatevBundle\CompanyCodes\SpecificCompanyCodes;
use Pickware\DatevBundle\Config\AccountAssignment\AccountAssignmentCustomer;
use Pickware\DatevBundle\Config\AccountAssignment\AccountAssignmentMetadata;
use Pickware\DatevBundle\Config\AccountAssignment\Item\DebtorAccountRequestItem;
use Pickware\DatevBundle\Config\AccountAssignment\Item\RevenueAccountRequestItem;
use Pickware\DatevBundle\Config\AccountAssignment\TaxStatus;
use Pickware\DatevBundle\Config\AccountRuleStackCreationService;
use Pickware\DatevBundle\Config\ConfigService;
use Pickware\DatevBundle\Config\Values\ConfigValues;
use Pickware\DatevBundle\CostCenters\CostCentersService;
use Pickware\DatevBundle\EntryBatch\DependencyInjection\EntryBatchRecordCreatorRegistry;
use Pickware\DatevBundle\EntryBatch\EntryBatchRecord;
use Pickware\DatevBundle\EntryBatch\EntryBatchRecordCollection;
use Pickware\DatevBundle\EntryBatch\EntryBatchRecordCreator;
use Pickware\DatevBundle\IndividualDebtorAccountInformation\ExportedIndividualDebtorService;
use Pickware\DatevBundle\OrderTaxValidation\TaxInformationValidator;
use Pickware\DatevBundle\PickwareDatevBundle;
use Pickware\DatevBundle\PosAccountingDocument\PosAccountingDocumentRequest\PosAccountingDocumentRequestCalculationContextFactory;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderCollection;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderEntity;
use Pickware\PickwarePos\BranchStore\BranchStoreNameProvider;
use Pickware\ShopwareExtensionsBundle\OrderTransaction\OrderTransactionCollectionExtension;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: EntryBatchRecordCreatorRegistry::DI_CONTAINER_TAG)]
class PosAccountingDocumentRecordCreator implements EntryBatchRecordCreator
{
    /**
     * The maximal deviation that is allowed when matching return receipts to their respective return orders, based off
     * the creation date of the return order and the document, in seconds. Required since there may be multiple return
     * order receipts, but they do not link to their respective return orders, so we have to use a heuristic.
     */
    private const RETURN_ORDER_RETURN_RECEIPT_MAXIMAL_TIME_DEVIATION_IN_SECONDS = 30;

    private const DOCUMENT_TYPE_DEBIT_CREDIT_MAPPING = [
        PickwareDatevBundle::PICKWARE_POS_RECEIPT_DOCUMENT_TYPE_TECHNICAL_NAME => EntryBatchRecord::CREDIT_IDENTIFIER,
        PickwareDatevBundle::PICKWARE_POS_RETURN_ORDER_RECEIPT_DOCUMENT_TYPE_TECHNICAL_NAME => EntryBatchRecord::DEBIT_IDENTIFIER,
    ];
    private const DOCUMENT_INFO_TYPE_SALES_CHANNEL = 'Verkaufskanal';
    private const DOCUMENT_INFO_TYPE_DOCUMENT_TYPE = 'Dokumententyp';
    private const DOCUMENT_INFO_TYPE_BRANCH_STORE = 'Filiale';
    private const ADDITIONAL_INFORMATION_TYPE_COMPANY = 'Firma';
    private const ADDITIONAL_INFORMATION_TYPE_TITLE = 'Titel';
    private const ADDITIONAL_INFORMATION_TYPE_FIRSTNAME = 'Vorname';
    private const ADDITIONAL_INFORMATION_TYPE_LASTNAME = 'Nachname';
    public const TECHNICAL_NAME = 'pos-accounting-document';

    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManager $entityManager,
        private readonly ConfigService $configService,
        private readonly AccountRuleStackCreationService $accountRuleStackCreationService,
        private readonly ?BranchStoreNameProvider $branchStoreNameProvider,
        private readonly AccountingDocumentGuidService $accountingDocumentGuidService,
        private readonly ExportedIndividualDebtorService $exportedIndividualDebtorService,
        private readonly CompanyCodesService $companyCodesService,
        private readonly CostCentersService $costCentersService,
        private readonly PosAccountingDocumentRequestCalculationContextFactory $calculationContextFactory,
        private readonly AccountingDocumentPriceItemCollectionFactory $priceItemCollectionFactory,
        private readonly TaxInformationValidator $orderTaxInformationValidator,
    ) {}

    /**
     * @param string[] $entityIds Ids of orders and return orders to be exported as returned by this domains
     * {@link PosAccountingDocumentEntityIdChunkCalculator}
     */
    public function createEntryBatchRecords(array $entityIds, array $exportConfig, string $exportId, Context $context): EntryBatchRecordCollection
    {
        if (count($entityIds) === 0) {
            return new EntryBatchRecordCollection();
        }

        $orderSalesChannelIds = $this->connection->fetchFirstColumn(
            <<<SQL
                SELECT DISTINCT LOWER(HEX(`order`.`sales_channel_id`)) AS `salesChannelId`
                FROM `order`
                WHERE `order`.`id` IN (:orderIds);
                SQL,
            ['orderIds' => array_map('hex2bin', $entityIds)],
            ['orderIds' => ArrayParameterType::BINARY],
        );
        $returnOrderSalesChannelIds = $this->connection->fetchFirstColumn(
            <<<SQL
                SELECT DISTINCT LOWER(HEX(`order`.`sales_channel_id`)) AS `salesChannelId`
                FROM `pickware_erp_return_order` returnOrder
                JOIN `order`
                    ON returnOrder.`order_id` = `order`.`id` AND returnOrder.`order_version_id` = `order`.`version_id`
                WHERE returnOrder.`id` IN (:returnOrderIds);
                SQL,
            ['returnOrderIds' => array_map('hex2bin', $entityIds)],
            ['returnOrderIds' => ArrayParameterType::BINARY],
        );
        $salesChannelIds = array_unique(array_merge($orderSalesChannelIds, $returnOrderSalesChannelIds));
        if (count($salesChannelIds) > 1) {
            throw new LogicException('Export for more than one sales channel is not supported at the moment.');
        }
        $salesChannelId = $salesChannelIds[0];

        $datevConfig = $this->configService->getConfig($salesChannelId, $context)->getValues();
        $exportConfig = PosAccountingDocumentExportConfig::fromExportConfig($exportConfig);

        $orderEntryCollection = $this->createEntryBatchRecordsForOrders(
            $entityIds,
            $exportId,
            $exportConfig,
            $datevConfig,
            $context,
        );

        $returnOrderEntryCollection = $this->createEntryBatchRecordsForReturnOrders(
            $entityIds,
            $exportId,
            $exportConfig,
            $datevConfig,
            $context,
        );

        $orderEntryCollection->mergeWith($returnOrderEntryCollection);

        return $orderEntryCollection;
    }

    public function getTechnicalName(): string
    {
        return self::TECHNICAL_NAME;
    }

    public function validateConfig(array $config): JsonApiErrors
    {
        return PosAccountingDocumentExportConfig::validate($config);
    }

    /**
     * @param string[] $documentTypeTechnicalNames
     */
    private function getAttachedDocumentsOfType(OrderEntity $order, array $documentTypeTechnicalNames): DocumentCollection
    {
        return $order
            ->getDocuments()
            ->filter(fn(DocumentEntity $document) => in_array($document->getDocumentType()->getTechnicalName(), $documentTypeTechnicalNames, true));
    }

    private function getDocumentNumber(DocumentEntity $document): ?string
    {
        return $document->getConfig()['documentNumber'] ?? null;
    }

    private function getReferenceNumber(
        OrderEntity $order,
        DocumentEntity $document,
        ConfigValues $datevConfig,
    ): string {
        if ($datevConfig->getPostingRecord()['documentReference'] === ConfigValues::POSTING_RECORD_DOCUMENT_REFERENCE_TYPE_ORDERNUMBER) {
            return $order->getOrderNumber();
        }

        return $this->getDocumentNumber($document) ?? '';
    }

    private function createPostingText(OrderEntity $order): string
    {
        return sprintf('%s, %s', $order->getOrderNumber(), $order->getOrderCustomer()?->getCustomerNumber() ?? '');
    }

    /**
     * @param string[] $orderIds
     */
    private function createEntryBatchRecordsForOrders(
        array $orderIds,
        string $exportId,
        PosAccountingDocumentExportConfig $exportConfig,
        ConfigValues $config,
        Context $context,
    ): EntryBatchRecordCollection {
        /** @var OrderCollection $orders */
        $orders = $this->entityManager->findBy(
            OrderDefinition::class,
            (new Criteria($orderIds))->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING)),
            $context,
            [
                'documents.documentType',
                'transactions.stateMachineState',
                'orderCustomer.customer.group',
                'salesChannel',
            ],
        );

        $branchStoreNamesByOrderId = [];
        if ($exportConfig->usePosDataModelAbstraction) {
            $branchStoreNamesByOrderId = $this->branchStoreNameProvider->getBranchStoreNamesByOrderId($orders->getIds(), $context);
        }

        $entryCollection = new EntryBatchRecordCollection();
        $exportedIndividualDebtorMaps = [];
        /** @var OrderEntity $order */
        foreach ($orders as $order) {
            $accountRuleStack = $this->accountRuleStackCreationService->createRevenueAccountRuleStack($config);
            $customer = $order->getOrderCustomer()?->getCustomer();
            $contraAccountRuleStack = $this->accountRuleStackCreationService->createDebtorAccountsRuleStack(
                $config,
                AccountAssignmentCustomer::fromRawCustomerData(
                    $customer?->getCustomerNumber(),
                    $customer?->getCustomFields(),
                ),
            );
            $receipts = $this->getAttachedDocumentsOfType($order, [PickwareDatevBundle::PICKWARE_POS_RECEIPT_DOCUMENT_TYPE_TECHNICAL_NAME]);
            $invoices = $this->getAttachedDocumentsOfType($order, [InvoiceRenderer::TYPE, ZugferdRenderer::TYPE, ZugferdEmbeddedRenderer::TYPE]);

            if ($receipts->count() === 0) {
                $entryCollection->addLogMessages(PosAccountingDocumentMessage::createNoReceiptsMessage($order->getOrderNumber()));

                continue;
            }

            if ($invoices->count() > 0) {
                $entryCollection->addLogMessages(PosAccountingDocumentMessage::createInvoiceExistsMessage(
                    orderNumber: $order->getOrderNumber(),
                    invoiceNumber: $this->getDocumentNumber($invoices->first()),
                    receiptNumber: $this->getDocumentNumber($receipts->first()),
                ));
            }

            if ($receipts->count() > 1) {
                $entryCollection->addLogMessages(PosAccountingDocumentMessage::createMoreThanOneReceiptMessage($order->getOrderNumber()));
            }

            $receipts->sort(fn(
                DocumentEntity $documentA,
                DocumentEntity $documentB,
            ) => $documentA->getCreatedAt() <=> $documentB->getCreatedAt());

            /** @var DocumentEntity $receipt */
            $receipt = $receipts->first();

            $documentTypeTechnicalName = $receipt->getDocumentType()->getTechnicalName();

            if (!$this->orderTaxInformationValidator->isOrderTaxInformationValid($order->getId(), $context)) {
                $entryCollection->addLogMessages(AccountingDocumentMessage::createIncompleteTaxInformationWarning(
                    $order->getOrderNumber(),
                    $this->getDocumentNumber($receipt),
                    $documentTypeTechnicalName,
                ));

                continue;
            }

            $calculationContext = $this->calculationContextFactory->createCalculationContextForOrder(
                $order->getId(),
                $context->createWithVersionId($order->getVersionId()),
            );
            $priceItemCollectionForOrderPrice = $this->priceItemCollectionFactory->createPriceItemCollection(
                $calculationContext->getCalculatableOrder()->price,
            );

            $priceItemCollectionForOrderPrice = $this->costCentersService->addCostCentersToPriceItems(
                $priceItemCollectionForOrderPrice,
                $calculationContext,
                $context,
            );

            /** @var ImmutableCollection<PosAccountingDocumentRequestItem> $requestItems */
            $requestItems = $priceItemCollectionForOrderPrice
                // Positions not contributing to the total should not be exported
                ->filter(fn(AccountingDocumentPriceItem $priceItem) => $priceItem->getPrice() !== 0.0)
                ->map(fn(AccountingDocumentPriceItem $priceItem) => new PosAccountingDocumentRequestItem(
                    price: $priceItem,
                    accountRequestItem: new RevenueAccountRequestItem(
                        key: sprintf('%s.%s', $order->getId(), $priceItem->getTaxRate()),
                        orderNumber: $order->getOrderNumber(),
                        taxStatus: TaxStatus::fromShopwareTaxStatus($order->getPrice()->getTaxStatus()),
                        taxRate: $priceItem->getTaxRate(),
                        countryIsoCode: null,
                        customerVatIds: null,
                        billedCompany: null,
                    ),
                    contraAccountRequestItem: new DebtorAccountRequestItem(
                        key: $order->getId(),
                        paymentMethodId: $calculationContext->getPaymentMethodId(),
                    ),
                ));

            $accountAssignmentMetadata = new AccountAssignmentMetadata(
                documentType: $receipt->getDocumentType()->getName() ?? 'unknown_document_type',
                documentNumber: $receipt->getDocumentNumber() ?? 'unknown_document_number',
                orderNumber: $order->getOrderNumber() ?? 'unknown_order_number',
            );
            $accountAssignments = $accountRuleStack->map(
                $requestItems->map(fn(PosAccountingDocumentRequestItem $item) => $item->getAccountRequestItem()),
                $accountAssignmentMetadata,
            );
            $contraAccountAssignments = $contraAccountRuleStack->map(
                $requestItems->map(fn(PosAccountingDocumentRequestItem $item) => $item->getContraAccountRequestItem()),
                $accountAssignmentMetadata,
            );

            $exportedIndividualDebtorMaps[] = $this->exportedIndividualDebtorService->getIndividualDebtorAccountInformationMap(
                $contraAccountAssignments->getAsImmutableCollection(),
                $customer?->getId(),
            );

            /** @var PosAccountingDocumentMessage[] $failuresForOrder */
            $failuresForOrder = [];
            $entryBatchRecordsForOrder = [];
            /** @var PosAccountingDocumentRequestItem $item */
            foreach ($requestItems as $item) {
                $revenue = $item->getPrice()->getPrice();

                $debitCreditIdentifier = self::DOCUMENT_TYPE_DEBIT_CREDIT_MAPPING[$documentTypeTechnicalName];
                if ($revenue < 0) {
                    $revenue *= -1;
                    $debitCreditIdentifier = EntryBatchRecord::DEBIT_CREDIT_INVERSION_MAPPING[$debitCreditIdentifier];
                }

                $accountAssignment = $accountAssignments->getByItem($item->getAccountRequestItem());
                $contraAccountAssignment = $contraAccountAssignments->getByItem($item->getContraAccountRequestItem());

                $accountAssignment = $this->companyCodesService->applyCompanyCode(
                    $config,
                    $accountAssignment,
                    SpecificCompanyCodes::createFromCustomFields(
                        $customer?->getCustomFields(),
                        $customer?->getGroup()?->getCustomFields(),
                    ),
                    new CompanyCodeMessageMetadata(
                        $customer?->getCustomerNumber() ?? 'unknown_customer_number',
                        $customer?->getGroup()?->getName() ?? 'unknown_customer_group',
                        $order->getSalesChannel()->getName() ?? 'unknown_sales_channel',
                        $receipt->getDocumentType()->getName() ?? 'unknown_document_type',
                        $this->getDocumentNumber($receipt),
                        $order->getOrderNumber(),
                    ),
                );

                $entryCollection->addLogMessages(...$accountAssignment->getMessages());
                $entryCollection->addLogMessages(...$contraAccountAssignment->getMessages());

                if ($accountAssignment->getAccountDetermination()->getAccount() === null) {
                    $failuresForOrder[] = PosAccountingDocumentMessage::createAccountUnresolvedForReceiptMessage(
                        $order->getOrderNumber(),
                        $this->getDocumentNumber($receipt),
                        $item->getPrice()->getTaxRate(),
                    );

                    continue;
                }

                if ($contraAccountAssignment->getAccountDetermination()->getAccount() === null) {
                    $failuresForOrder[] = PosAccountingDocumentMessage::createContraAccountUnresolvedForReceiptMessage(
                        $order->getOrderNumber(),
                        $this->getDocumentNumber($receipt),
                        $item->getPrice()->getTaxRate(),
                    );

                    continue;
                }

                $receiptLink = $this->accountingDocumentGuidService->ensureAccountingDocumentGuidExistsForDocumentId($receipt->getId(), $exportId, $context);
                $costCenters = $this->costCentersService->getCostCenters(
                    $item->getPrice(),
                    $config,
                    $order->getSalesChannel()->getName() ?? 'unknown_sales_channel',
                    $receipt->getDocumentType()->getName() ?? 'unknown_document_type',
                    $receipt->getDocumentNumber() ?? 'unknown_document_number',
                    $order->getOrderNumber() ?? 'unknown_order_number',
                );

                $entryCollection->addLogMessages(...$costCenters->getMessages());

                $entryBatchRecordsForOrder[] = new EntryBatchRecord(
                    revenue: $revenue,
                    debitCreditIdentifier: $debitCreditIdentifier,
                    account: $accountAssignment->getAccountDetermination()->getAccount(),
                    contraAccount: $contraAccountAssignment->getAccountDetermination()->getAccount(),
                    documentDate: $order->getCreatedAt(),
                    documentField1: $this->getReferenceNumber($order, $receipt, $config),
                    postingText: $this->createPostingText($order),
                    receiptLink: $receiptLink,
                    documentInfoType1: self::DOCUMENT_INFO_TYPE_SALES_CHANNEL,
                    documentInfoContent1: $order->getSalesChannel()->getName(),
                    documentInfoType2: self::DOCUMENT_INFO_TYPE_DOCUMENT_TYPE,
                    documentInfoContent2: $receipt->getDocumentType()->getName(),
                    documentInfoType3: self::DOCUMENT_INFO_TYPE_BRANCH_STORE,
                    documentInfoContent3: $branchStoreNamesByOrderId[$order->getId()] ?? null,
                    documentInfoType4: null,
                    documentInfoContent4: null,
                    costCenter1: $costCenters->getCostCenter1(),
                    costCenter2: $costCenters->getCostCenter2(),
                    euCountryAndVatId: null,
                    euTaxRate: null,
                    additionalInformationType1: $order->getOrderCustomer()?->getCompany() !== null ? self::ADDITIONAL_INFORMATION_TYPE_COMPANY : null,
                    additionalInformationContent1: $order->getOrderCustomer()?->getCompany(),
                    additionalInformationType2: $order->getOrderCustomer()?->getTitle() !== null ? self::ADDITIONAL_INFORMATION_TYPE_TITLE : null,
                    additionalInformationContent2: $order->getOrderCustomer()?->getTitle(),
                    additionalInformationType3: $order->getOrderCustomer()?->getFirstName() !== null ? self::ADDITIONAL_INFORMATION_TYPE_FIRSTNAME : null,
                    additionalInformationContent3: $order->getOrderCustomer()?->getFirstName(),
                    additionalInformationType4: $order->getOrderCustomer()?->getLastName() !== null ? self::ADDITIONAL_INFORMATION_TYPE_LASTNAME : null,
                    additionalInformationContent4: $order->getOrderCustomer()?->getLastName(),
                    fixation: false,
                    taskNumber: $order->getOrderNumber(),
                );
            }

            $this->exportedIndividualDebtorService->ensureIndividualDebtorAccountInformationExistsForAccountsAndExport(
                $exportedIndividualDebtorMaps,
                $exportId,
                $context,
            );

            if (count($failuresForOrder) > 0) {
                $entryCollection->addLogMessages(...$failuresForOrder);
            } else {
                $entryCollection->addEntries(...$entryBatchRecordsForOrder);
            }
        }

        return $entryCollection;
    }

    /**
     * @param string[] $returnOrderIds
     */
    private function createEntryBatchRecordsForReturnOrders(
        array $returnOrderIds,
        string $exportId,
        PosAccountingDocumentExportConfig $exportConfig,
        ConfigValues $config,
        Context $context,
    ): EntryBatchRecordCollection {
        /** @var ReturnOrderCollection $returnOrders */
        $returnOrders = $this->entityManager->findBy(
            ReturnOrderDefinition::class,
            (new Criteria($returnOrderIds))->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING)),
            $context,
            [
                'order.documents.documentType',
                'order.transactions.stateMachineState',
                'order.orderCustomer.customer.group',
                'order.salesChannel',
                'refund',
            ],
        );

        $branchStoreNamesByOrderId = [];
        if ($exportConfig->usePosDataModelAbstraction) {
            $orderIds = $returnOrders->map(fn(ReturnOrderEntity $returnOrder) => $returnOrder->getOrderId());
            $branchStoreNamesByOrderId = $this->branchStoreNameProvider->getBranchStoreNamesByOrderId($orderIds, $context);
        }

        $entryCollection = new EntryBatchRecordCollection();
        $exportedIndividualDebtorMaps = [];
        /** @var ReturnOrderEntity $returnOrder */
        foreach ($returnOrders as $returnOrder) {
            $accountRuleStack = $this->accountRuleStackCreationService->createRevenueAccountRuleStack($config);
            $customer = $returnOrder->getOrder()->getOrderCustomer()?->getCustomer();
            $contraAccountRuleStack = $this->accountRuleStackCreationService->createDebtorAccountsRuleStack(
                $config,
                AccountAssignmentCustomer::fromRawCustomerData(
                    $customer?->getCustomerNumber(),
                    $customer?->getCustomFields(),
                ),
            );
            $returnReceipts = $this
                ->getAttachedDocumentsOfType(
                    $returnOrder->getOrder(),
                    [PickwareDatevBundle::PICKWARE_POS_RETURN_ORDER_RECEIPT_DOCUMENT_TYPE_TECHNICAL_NAME],
                )
                ->filter(fn(DocumentEntity $document): bool => $document->getCreatedAt()
                            ->diff($returnOrder->getCreatedAt(), absolute: true)
                            ->s <= self::RETURN_ORDER_RETURN_RECEIPT_MAXIMAL_TIME_DEVIATION_IN_SECONDS);

            if ($returnReceipts->count() === 0) {
                $entryCollection->addLogMessages(PosAccountingDocumentMessage::createNoReturnReceiptsMessage($returnOrder->getNumber()));

                continue;
            }

            if ($returnReceipts->count() > 1) {
                $entryCollection->addLogMessages(PosAccountingDocumentMessage::createMoreThanOneReturnReceiptMessage($returnOrder->getNumber()));
            }

            $returnReceipts->sort(fn(
                DocumentEntity $documentA,
                DocumentEntity $documentB,
            ) => $documentA->getCreatedAt() <=> $documentB->getCreatedAt());

            /** @var DocumentEntity $returnReceipt */
            $returnReceipt = $returnReceipts->first();

            $documentTypeTechnicalName = $returnReceipt->getDocumentType()->getTechnicalName();
            $calculationContext = $this->calculationContextFactory->createCalculationContextForReturnOrder(
                $returnOrder->getId(),
                $context->createWithVersionId($returnOrder->getVersionId()),
            );
            $priceItemCollectionForOrderPrice = $this->priceItemCollectionFactory->createPriceItemCollection(
                $calculationContext->getCalculatableOrder()->price,
            );

            $priceItemCollectionForOrderPrice = $this->costCentersService->addCostCentersToPriceItems(
                $priceItemCollectionForOrderPrice,
                $calculationContext,
                $context,
            );

            $transactionsPaymentMethodId = OrderTransactionCollectionExtension::getPrimaryOrderTransaction(
                $returnOrder->getOrder()->getTransactions(),
            )->getPaymentMethodId();

            /** @var ImmutableCollection<PosAccountingDocumentRequestItem> $requestItems */
            $requestItems = $priceItemCollectionForOrderPrice
                // Positions not contributing to the total should not be exported
                ->filter(fn(AccountingDocumentPriceItem $priceItem) => $priceItem->getPrice() !== 0.0)
                ->map(fn(AccountingDocumentPriceItem $priceItem) => new PosAccountingDocumentRequestItem(
                    price: $priceItem,
                    accountRequestItem: new RevenueAccountRequestItem(
                        key: sprintf('%s.%s', $returnOrder->getId(), $priceItem->getTaxRate()),
                        orderNumber: $returnOrder->getOrder()->getOrderNumber(),
                        taxStatus: TaxStatus::fromShopwareTaxStatus($returnOrder->getPrice()->getTaxStatus()),
                        taxRate: $priceItem->getTaxRate(),
                        countryIsoCode: null,
                        customerVatIds: null,
                        billedCompany: null,
                    ),
                    contraAccountRequestItem: new DebtorAccountRequestItem(
                        key: $returnOrder->getId(),
                        paymentMethodId: $returnOrder->getRefund()?->getPaymentMethodId() ?? $transactionsPaymentMethodId,
                    ),
                ));

            $accountAssignmentMetadata = new AccountAssignmentMetadata(
                documentType: $returnReceipt->getDocumentType()->getName() ?? 'unknown_document_type',
                documentNumber: $returnReceipt->getDocumentNumber() ?? 'unknown_document_number',
                orderNumber: $returnOrder->getOrder()->getOrderNumber() ?? 'unknown_order_number',
            );
            $accountAssignments = $accountRuleStack->map(
                $requestItems->map(fn(PosAccountingDocumentRequestItem $item) => $item->getAccountRequestItem()),
                $accountAssignmentMetadata,
            );
            $contraAccountAssignments = $contraAccountRuleStack->map(
                $requestItems->map(fn(PosAccountingDocumentRequestItem $item) => $item->getContraAccountRequestItem()),
                $accountAssignmentMetadata,
            );

            $exportedIndividualDebtorMaps[] = $this->exportedIndividualDebtorService->getIndividualDebtorAccountInformationMap(
                $contraAccountAssignments->getAsImmutableCollection(),
                $customer?->getId(),
            );

            /** @var PosAccountingDocumentMessage[] $failuresForOrder */
            $failuresForOrder = [];
            $entryBatchRecordsForOrder = [];
            /** @var PosAccountingDocumentRequestItem $item */
            foreach ($requestItems as $item) {
                $revenue = $item->getPrice()->getPrice();

                $debitCreditIdentifier = self::DOCUMENT_TYPE_DEBIT_CREDIT_MAPPING[$documentTypeTechnicalName];
                if ($revenue < 0) {
                    $revenue *= -1;
                    $debitCreditIdentifier = EntryBatchRecord::DEBIT_CREDIT_INVERSION_MAPPING[$debitCreditIdentifier];
                }

                $accountAssignmentResultItem = $accountAssignments->getByItem($item->getAccountRequestItem());
                $contraAccountAssignmentResultItem = $contraAccountAssignments->getByItem($item->getContraAccountRequestItem());

                $accountAssignmentResultItem = $this->companyCodesService->applyCompanyCode(
                    $config,
                    $accountAssignmentResultItem,
                    SpecificCompanyCodes::createFromCustomFields(
                        $customer?->getCustomFields(),
                        $customer?->getGroup()?->getCustomFields(),
                    ),
                    new CompanyCodeMessageMetadata(
                        $customer?->getCustomerNumber() ?? 'unknown_customer_number',
                        $customer?->getGroup()?->getName() ?? 'unknown_customer_group',
                        $returnOrder->getOrder()->getSalesChannel()->getName() ?? 'unknown_sales_channel',
                        $returnReceipt->getDocumentType()->getName() ?? 'unknown_document_type',
                        $this->getDocumentNumber($returnReceipt),
                        $returnOrder->getOrder()->getOrderNumber(),
                    ),
                );

                $entryCollection->addLogMessages(...$accountAssignmentResultItem->getMessages());
                $entryCollection->addLogMessages(...$contraAccountAssignmentResultItem->getMessages());

                if (!$accountAssignmentResultItem->getAccountDetermination()->getAccount()) {
                    $failuresForOrder[] = PosAccountingDocumentMessage::createAccountUnresolvedForReturnReceiptMessage(
                        $returnOrder->getNumber(),
                        $this->getDocumentNumber($returnReceipt),
                        $item->getPrice()->getTaxRate(),
                    );
                }

                if (!$contraAccountAssignmentResultItem->getAccountDetermination()->getAccount()) {
                    $failuresForOrder[] = PosAccountingDocumentMessage::createContraAccountUnresolvedForReturnReceiptMessage(
                        $returnOrder->getNumber(),
                        $this->getDocumentNumber($returnReceipt),
                        $item->getPrice()->getTaxRate(),
                    );
                }

                if (count($failuresForOrder) > 0) {
                    continue;
                }

                $receiptLink = $this->accountingDocumentGuidService->ensureAccountingDocumentGuidExistsForDocumentId($returnReceipt->getId(), $exportId, $context);
                $costCenters = $this->costCentersService->getCostCenters(
                    $item->getPrice(),
                    $config,
                    $returnOrder->getOrder()->getSalesChannel()->getName() ?? 'unknown_sales_channel',
                    $returnReceipt->getDocumentType()->getName() ?? 'unknown_document_type',
                    $returnReceipt->getDocumentNumber() ?? 'unknown_document_number',
                    $returnOrder->getOrder()->getOrderNumber() ?? 'unknown_order_number',
                );

                $entryCollection->addLogMessages(...$costCenters->getMessages());

                $entryBatchRecordsForOrder[] = new EntryBatchRecord(
                    revenue: $revenue,
                    debitCreditIdentifier: $debitCreditIdentifier,
                    account: $accountAssignmentResultItem->getAccountDetermination()->getAccount(),
                    contraAccount: $contraAccountAssignmentResultItem->getAccountDetermination()->getAccount(),
                    documentDate: $returnOrder->getCreatedAt(),
                    documentField1: $this->getReferenceNumber($returnOrder->getOrder(), $returnReceipt, $config),
                    postingText: $this->createPostingText($returnOrder->getOrder()),
                    receiptLink: $receiptLink,
                    documentInfoType1: self::DOCUMENT_INFO_TYPE_SALES_CHANNEL,
                    documentInfoContent1: $returnOrder->getOrder()->getSalesChannel()->getName(),
                    documentInfoType2: self::DOCUMENT_INFO_TYPE_DOCUMENT_TYPE,
                    documentInfoContent2: $returnReceipt->getDocumentType()->getName(),
                    documentInfoType3: self::DOCUMENT_INFO_TYPE_BRANCH_STORE,
                    documentInfoContent3: $branchStoreNamesByOrderId[$returnOrder->getOrderId()] ?? null,
                    documentInfoType4: null,
                    documentInfoContent4: null,
                    costCenter1: $costCenters->getCostCenter1(),
                    costCenter2: $costCenters->getCostCenter2(),
                    euCountryAndVatId: null,
                    euTaxRate: null,
                    additionalInformationType1: $returnOrder->getOrder()->getOrderCustomer()?->getCompany() !== null ? self::ADDITIONAL_INFORMATION_TYPE_COMPANY : null,
                    additionalInformationContent1: $returnOrder->getOrder()->getOrderCustomer()?->getCompany(),
                    additionalInformationType2: $returnOrder->getOrder()->getOrderCustomer()?->getTitle() !== null ? self::ADDITIONAL_INFORMATION_TYPE_TITLE : null,
                    additionalInformationContent2: $returnOrder->getOrder()->getOrderCustomer()?->getTitle(),
                    additionalInformationType3: $returnOrder->getOrder()->getOrderCustomer()?->getFirstName() !== null ? self::ADDITIONAL_INFORMATION_TYPE_FIRSTNAME : null,
                    additionalInformationContent3: $returnOrder->getOrder()->getOrderCustomer()?->getFirstName(),
                    additionalInformationType4: $returnOrder->getOrder()->getOrderCustomer()?->getLastName() !== null ? self::ADDITIONAL_INFORMATION_TYPE_LASTNAME : null,
                    additionalInformationContent4: $returnOrder->getOrder()->getOrderCustomer()?->getLastName(),
                    fixation: false,
                    taskNumber: $returnOrder->getOrder()->getOrderNumber(),
                );
            }

            $this->exportedIndividualDebtorService->ensureIndividualDebtorAccountInformationExistsForAccountsAndExport(
                $exportedIndividualDebtorMaps,
                $exportId,
                $context,
            );

            if (count($failuresForOrder) > 0) {
                $entryCollection->addLogMessages(...$failuresForOrder);
            } else {
                $entryCollection->addEntries(...$entryBatchRecordsForOrder);
            }
        }

        return $entryCollection;
    }
}
