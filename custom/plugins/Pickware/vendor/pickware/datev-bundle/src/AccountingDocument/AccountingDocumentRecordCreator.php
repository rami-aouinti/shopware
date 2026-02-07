<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\AccountingDocument;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use LogicException;
use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\AccountingDocument\AccountingDocumentRequest\AccountingDocumentPriceItem;
use Pickware\DatevBundle\AccountingDocument\AccountingDocumentRequest\AccountingDocumentRequestFactory;
use Pickware\DatevBundle\AccountingDocument\AccountingDocumentRequest\AccountingDocumentRequestItem;
use Pickware\DatevBundle\AccountingDocumentPicture\Guid\AccountingDocumentGuidService;
use Pickware\DatevBundle\CompanyCodes\CompanyCodeMessageMetadata;
use Pickware\DatevBundle\CompanyCodes\CompanyCodesService;
use Pickware\DatevBundle\CompanyCodes\SpecificCompanyCodes;
use Pickware\DatevBundle\Config\AccountAssignment\AccountAssignmentCustomer;
use Pickware\DatevBundle\Config\AccountAssignment\AccountAssignmentMetadata;
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
use Pickware\DatevBundle\TaskNumber\TaskNumberProvider;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\InvoiceCorrection\InvoiceCorrectionConfigGenerator;
use Pickware\PickwareErpStarter\InvoiceCorrection\InvoiceCorrectionDocumentType;
use Pickware\PickwareErpStarter\InvoiceCorrection\Model\PickwareDocumentVersionEntity;
use Pickware\ShopwareExtensionsBundle\OrderDelivery\OrderDeliveryCollectionExtension;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\StornoRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: EntryBatchRecordCreatorRegistry::DI_CONTAINER_TAG)]
class AccountingDocumentRecordCreator implements EntryBatchRecordCreator
{
    private const DOCUMENT_TYPE_DEBIT_CREDIT_MAPPING = [
        InvoiceRenderer::TYPE => EntryBatchRecord::CREDIT_IDENTIFIER,
        ZugferdRenderer::TYPE => EntryBatchRecord::CREDIT_IDENTIFIER,
        ZugferdEmbeddedRenderer::TYPE => EntryBatchRecord::CREDIT_IDENTIFIER,
        StornoRenderer::TYPE => EntryBatchRecord::DEBIT_IDENTIFIER,
        InvoiceCorrectionDocumentType::TECHNICAL_NAME => EntryBatchRecord::CREDIT_IDENTIFIER,
        PickwareDatevBundle::PICKWARE_POS_RECEIPT_DOCUMENT_TYPE_TECHNICAL_NAME => EntryBatchRecord::CREDIT_IDENTIFIER,
    ];
    private const DOCUMENT_INFO_TYPE_SALES_CHANNEL = 'Verkaufskanal';
    private const DOCUMENT_INFO_TYPE_DOCUMENT_TYPE = 'Dokumententyp';
    private const DOCUMENT_INFO_TYPE_REFERENCED_INVOICE = 'Referenzierte Rg.';
    private const ADDITIONAL_INFORMATION_TYPE_COMPANY = 'Firma';
    private const ADDITIONAL_INFORMATION_TYPE_TITLE = 'Titel';
    private const ADDITIONAL_INFORMATION_TYPE_FIRSTNAME = 'Vorname';
    private const ADDITIONAL_INFORMATION_TYPE_LASTNAME = 'Nachname';
    public const TECHNICAL_NAME = 'accounting-document';

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Connection $db,
        private readonly ConfigService $configService,
        private readonly AccountRuleStackCreationService $accountRuleStackCreationService,
        private readonly AccountingDocumentRequestFactory $accountingDocumentRequestFactory,
        private readonly AccountingDocumentGuidService $accountingDocumentGuidService,
        private readonly ExportedIndividualDebtorService $exportedIndividualDebtorService,
        private readonly CompanyCodesService $companyCodesService,
        private readonly CostCentersService $costCentersService,
        private readonly TaxInformationValidator $orderTaxInformationValidator,
        private readonly TaskNumberProvider $taskNumberProvider,
    ) {}

    /**
     * @param string[] $entityIds Ids of documents to be exported as returned by this domains
     * {@link AccountingDocumentEntityIdChunkCalculator}
     */
    public function createEntryBatchRecords(array $entityIds, array $exportConfig, string $exportId, Context $context): EntryBatchRecordCollection
    {
        if (count($entityIds) === 0) {
            return new EntryBatchRecordCollection();
        }

        $salesChannelIds = $this->db->fetchAllAssociative(
            <<<SQL
                SELECT DISTINCT LOWER(HEX(`order`.`sales_channel_id`)) AS `salesChannelId`
                FROM `document`
                JOIN `order` ON `document`.`order_id` = `order`.`id`
                    AND `document`.`order_version_id` = `order`.`version_id`
                WHERE `document`.`id` IN (:entityIds);
                SQL,
            ['entityIds' => array_map('hex2bin', $entityIds)],
            ['entityIds' => ArrayParameterType::BINARY],
        );
        if (count($salesChannelIds) > 1) {
            throw new LogicException('Export for more than one sales channel is not supported at the moment.');
        }
        $salesChannelId = $salesChannelIds[0]['salesChannelId'];

        $datevConfig = $this->configService->getConfig($salesChannelId, $context)->getValues();

        $documents = $this->entityManager->findBy(
            DocumentDefinition::class,
            (new Criteria($entityIds))->addSorting(new FieldSorting('config.documentDate', FieldSorting::ASCENDING)),
            $context,
            [
                'documentType',
                'referencedDocument',
                'pickwareErpDocumentVersion',
            ],
        );

        $entryCollection = new EntryBatchRecordCollection();
        $exportedIndividualDebtorMaps = [];
        /** @var DocumentEntity $document */
        foreach ($documents as $document) {
            /** @var PickwareDocumentVersionEntity|null $pickwareDocumentVersion */
            $pickwareDocumentVersion = $document->getExtension('pickwareErpDocumentVersion');

            $orderVersionContext = $context->createWithVersionId($pickwareDocumentVersion?->getOrderVersionId() ?? $document->getOrderVersionId());
            /** @var OrderEntity $order */
            $order = $this->entityManager->getByPrimaryKey(
                OrderDefinition::class,
                $document->getOrderId(),
                $orderVersionContext,
                [
                    'deliveries',
                    'orderCustomer.customer.group',
                    'salesChannel',
                ],
            );

            $orderNumber = $order->getOrderNumber();
            $documentTypeTechnicalName = $document->getDocumentType()->getTechnicalName();

            // POS documents always point to the live version of an order.
            // Therefore, they must be excluded when checking the live version of the order.
            // See: https://github.com/pickware/shopware-plugins/issues/11334
            if (
                $order->getVersionId() === Defaults::LIVE_VERSION
                && $documentTypeTechnicalName !== PickwareDatevBundle::PICKWARE_POS_RECEIPT_DOCUMENT_TYPE_TECHNICAL_NAME
            ) {
                $entryCollection->addLogMessages(AccountingDocumentMessage::createDocumentHasNoOrderVersionError(
                    $orderNumber,
                    $this->getDocumentNumber($document->getConfig()),
                    $documentTypeTechnicalName,
                ));

                continue;
            }

            if (!$this->configHasValidDocumentDate($document->getConfig())) {
                $entryCollection->addLogMessages(AccountingDocumentMessage::createDocumentDateMissingWarning(
                    $orderNumber,
                    $this->getDocumentNumber($document->getConfig()),
                    $documentTypeTechnicalName,
                ));
            }

            if (!$this->orderTaxInformationValidator->isOrderTaxInformationValid($order->getId(), $orderVersionContext)) {
                $entryCollection->addLogMessages(AccountingDocumentMessage::createIncompleteTaxInformationWarning(
                    $order->getOrderNumber(),
                    $this->getDocumentNumber($document->getConfig()),
                    $documentTypeTechnicalName,
                ));

                continue;
            }

            if ($documentTypeTechnicalName === ZugferdRenderer::TYPE) {
                $entryCollection->addLogMessages(AccountingDocumentMessage::createZugferdDocumentNoBelegbildWarning(
                    $orderNumber,
                    $this->getDocumentNumber($document->getConfig()),
                ));
            }

            // Skip export if the referenced document (using its order version) has missing/invalid tax data.
            // This prevents export of documents linked to a faulty invoice.
            // See: https://github.com/pickware/shopware-plugins/issues/8263#issuecomment-2704074890
            $referencedDocumentId = $document->getReferencedDocument()?->getId() ?? $document->getConfig()['custom']['pickwareErpReferencedDocumentId'] ?? null;
            if ($referencedDocumentId && !$this->orderTaxInformationValidator->isDocumentTaxInformationValid($referencedDocumentId, $orderVersionContext)) {
                $entryCollection->addLogMessages(AccountingDocumentMessage::createIncompleteTaxInformationWarning(
                    $order->getOrderNumber(),
                    $this->getDocumentNumber($document->getConfig()),
                    $documentTypeTechnicalName,
                ));

                continue;
            }

            $request = $this->accountingDocumentRequestFactory->createAccountingDocumentRequestForDocument(
                $document->getId(),
                $context,
            );

            $accountRuleStack = $this->accountRuleStackCreationService->createRevenueAccountRuleStack($datevConfig);
            $customer = $order->getOrderCustomer()?->getCustomer();
            $contraAccountRuleStack = $this->accountRuleStackCreationService->createDebtorAccountsRuleStack(
                $datevConfig,
                AccountAssignmentCustomer::fromRawCustomerData(
                    $customer?->getCustomerNumber(),
                    $customer?->getCustomFields(),
                ),
            );
            $accountAssignmentMetadata = new AccountAssignmentMetadata(
                documentType: $document->getDocumentType()->getName() ?? 'unknown_document_type',
                documentNumber: $document->getDocumentNumber() ?? 'unknown_document_number',
                orderNumber: $orderNumber ?? 'unknown_order_number',
            );
            $accountAssignments = $accountRuleStack->map(
                $request->getAccountRequestItems(),
                $accountAssignmentMetadata,
            );
            $contraAccountAssignments = $contraAccountRuleStack->map(
                $request->getContraAccountRequestItems(),
                $accountAssignmentMetadata,
            );

            $exportedIndividualDebtorMaps[] = $this->exportedIndividualDebtorService->getIndividualDebtorAccountInformationMap(
                $contraAccountAssignments->getAsImmutableCollection(),
                $customer?->getId(),
            );

            $entryBatchRecordsForDocument = [];
            /** @var AccountingDocumentMessage[] $failuresForDocument */
            $failuresForDocument = [];
            foreach ($request->getItems() as $item) {
                $revenue = $item->getPrice()->getPrice();
                $debitCreditIdentifier = self::DOCUMENT_TYPE_DEBIT_CREDIT_MAPPING[$documentTypeTechnicalName];
                if ($revenue < 0) {
                    $revenue *= -1;
                    $debitCreditIdentifier = EntryBatchRecord::DEBIT_CREDIT_INVERSION_MAPPING[$debitCreditIdentifier];
                }

                $accountAssignmentResultItem = $accountAssignments->getByItem($item->getAccountRequestItem());
                $contraAccountAssignmentResultItem = $contraAccountAssignments->getByItem($item->getContraAccountRequestItem());

                $accountAssignmentResultItem = $this->companyCodesService->applyCompanyCode(
                    $datevConfig,
                    $accountAssignmentResultItem,
                    SpecificCompanyCodes::createFromCustomFields(
                        $customer?->getCustomFields(),
                        $customer?->getGroup()?->getCustomFields(),
                    ),
                    new CompanyCodeMessageMetadata(
                        $customer?->getCustomerNumber() ?? 'unknown_customer',
                        $customer?->getGroup()?->getName() ?? 'unknown_customer_group',
                        $order->getSalesChannel()->getName() ?? 'unknown_sales_channel',
                        $document->getDocumentType()->getName() ?? 'unknown_document_type',
                        $document->getDocumentNumber() ?? 'unknown_document_number',
                        $orderNumber ?? 'unknown_order_number',
                    ),
                );

                $entryCollection->addLogMessages(...$accountAssignmentResultItem->getMessages());
                $entryCollection->addLogMessages(...$contraAccountAssignmentResultItem->getMessages());

                if ($accountAssignmentResultItem->getAccountDetermination()->getAccount() === null) {
                    $failuresForDocument[] = AccountingDocumentMessage::createAccountUnresolvedError(
                        $order->getOrderNumber(),
                        $this->getDocumentNumber($document->getConfig()),
                        $item->getPrice()->getTaxRate(),
                        $documentTypeTechnicalName,
                    );

                    continue;
                }

                if ($contraAccountAssignmentResultItem->getAccountDetermination()->getAccount() === null) {
                    $failuresForDocument[] = AccountingDocumentMessage::createContraAccountUnresolvedError(
                        $order->getOrderNumber(),
                        $this->getDocumentNumber($document->getConfig()),
                        $item->getPrice()->getTaxRate(),
                        $documentTypeTechnicalName,
                    );

                    continue;
                }

                [
                    $referenceInfoType,
                    $referenceInfoContent,
                ] = $this->getInvoiceReferenceInformation($document);

                $receiptLink = $this->accountingDocumentGuidService->ensureAccountingDocumentGuidExistsForDocumentId($document->getId(), $exportId, $context);
                $costCenters = $this->costCentersService->getCostCenters(
                    $item->getPrice(),
                    $datevConfig,
                    $order->getSalesChannel()->getName() ?? 'unknown_sales_channel',
                    $document->getDocumentType()->getName() ?? 'unknown_document_type',
                    $document->getDocumentNumber() ?? 'unknown_document_number',
                    $orderNumber ?? 'unknown_order_number',
                );

                $entryCollection->addLogMessages(...$costCenters->getMessages());

                $taskNumberProviderResult = $this->taskNumberProvider->getTaskNumberForDocument(
                    $order->getId(),
                    $order->getVersionId(),
                    $document->getId(),
                    $datevConfig,
                    $context,
                );
                $entryCollection->addLogMessages(...$taskNumberProviderResult->logMessages);

                $entryBatchRecordsForDocument[] = new EntryBatchRecord(
                    revenue: $revenue,
                    debitCreditIdentifier: $debitCreditIdentifier,
                    account: $accountAssignmentResultItem->getAccountDetermination()->getAccount(),
                    contraAccount: $contraAccountAssignmentResultItem->getAccountDetermination()->getAccount(),
                    documentDate: $this->getReferenceDateForDocument($document),
                    documentField1: $this->getReferenceNumber($order, $document, $datevConfig),
                    postingText: $this->getPostingText($order),
                    receiptLink: $receiptLink,
                    documentInfoType1: self::DOCUMENT_INFO_TYPE_SALES_CHANNEL,
                    documentInfoContent1: $order->getSalesChannel()->getName(),
                    documentInfoType2: self::DOCUMENT_INFO_TYPE_DOCUMENT_TYPE,
                    documentInfoContent2: $document->getDocumentType()->getName(),
                    documentInfoType3: $referenceInfoType,
                    documentInfoContent3: $referenceInfoContent,
                    documentInfoType4: null,
                    documentInfoContent4: null,
                    costCenter1: $costCenters->getCostCenter1(),
                    costCenter2: $costCenters->getCostCenter2(),
                    euCountryAndVatId: $this->getEuCountryAndVatId($item),
                    euTaxRate: $this->getEuTaxRateForPrice($item->getPrice(), $item->getAccountRequestItem()->getCountryIsoCode()),
                    additionalInformationType1: $order->getOrderCustomer()?->getCompany() !== null ? self::ADDITIONAL_INFORMATION_TYPE_COMPANY : null,
                    additionalInformationContent1: $order->getOrderCustomer()?->getCompany(),
                    additionalInformationType2: $order->getOrderCustomer()?->getTitle() !== null ? self::ADDITIONAL_INFORMATION_TYPE_TITLE : null,
                    additionalInformationContent2: $order->getOrderCustomer()?->getTitle(),
                    additionalInformationType3: $order->getOrderCustomer()?->getFirstName() !== null ? self::ADDITIONAL_INFORMATION_TYPE_FIRSTNAME : null,
                    additionalInformationContent3: $order->getOrderCustomer()?->getFirstName(),
                    additionalInformationType4: $order->getOrderCustomer()?->getLastName() !== null ? self::ADDITIONAL_INFORMATION_TYPE_LASTNAME : null,
                    additionalInformationContent4: $order->getOrderCustomer()?->getLastName(),
                    fixation: false,
                    taskNumber: $taskNumberProviderResult->taskNumber,
                );
            }

            $this->exportedIndividualDebtorService->ensureIndividualDebtorAccountInformationExistsForAccountsAndExport(
                $exportedIndividualDebtorMaps,
                $exportId,
                $context,
            );

            $primaryDelivery = OrderDeliveryCollectionExtension::primaryOrderDelivery($order->getDeliveries());
            if (!$primaryDelivery) {
                $entryCollection->addLogMessages(AccountingDocumentMessage::createNoShippingAddressInfo(
                    $orderNumber,
                    $this->getDocumentNumber($document->getConfig()),
                    $documentTypeTechnicalName,
                ));
            }

            if (count($failuresForDocument) > 0) {
                $entryCollection->addLogMessages(...$failuresForDocument);
            } else {
                $entryCollection->addEntries(...$entryBatchRecordsForDocument);
            }
        }

        return $entryCollection;
    }

    public function getTechnicalName(): string
    {
        return self::TECHNICAL_NAME;
    }

    public function validateConfig(array $config): JsonApiErrors
    {
        return AccountingDocumentExportConfig::validate($config);
    }

    /**
     * @param array{documentDate?: ?string} $documentConfig
     */
    private function configHasValidDocumentDate(array $documentConfig): bool
    {
        return ($documentConfig['documentDate'] ?? null) !== null;
    }

    private function getDocumentNumber(array $documentConfig): ?string
    {
        return $documentConfig['documentNumber'] ?? null;
    }

    private function getInvoiceReferenceInformation(DocumentEntity $document): array
    {
        $documentTypeTechnicalName = $document->getDocumentType()->getTechnicalName();
        $hasInvoiceReference = in_array($documentTypeTechnicalName, [
            StornoRenderer::TYPE,
            InvoiceCorrectionDocumentType::TECHNICAL_NAME,
        ], true);
        if (!$hasInvoiceReference) {
            return [
                null,
                null,
            ];
        }

        if ($documentTypeTechnicalName === StornoRenderer::TYPE) {
            return [
                self::DOCUMENT_INFO_TYPE_REFERENCED_INVOICE,
                $this->getDocumentNumber($document->getReferencedDocument()->getConfig()),
            ];
        }

        return [
            self::DOCUMENT_INFO_TYPE_REFERENCED_INVOICE,
            $document->getConfig()['custom'][
                InvoiceCorrectionConfigGenerator::DOCUMENT_CONFIGURATION_REFERENCED_INVOICE_NUMBER_KEY
            ],
        ];
    }

    private function getReferenceDateForDocument(DocumentEntity $document): DateTimeInterface
    {
        $documentDate = $document->getConfig()['documentDate'] ?? null;
        if ($documentDate !== null) {
            return new DateTimeImmutable($documentDate);
        }

        return $document->getCreatedAt();
    }

    private function getReferenceNumber(
        OrderEntity $order,
        DocumentEntity $document,
        ConfigValues $datevConfig,
    ): string {
        $referenceNumber = $this->getDocumentNumber($document->getConfig()) ?? '';
        if (($datevConfig->getPostingRecord()['documentReference'] ?? null) === ConfigValues::POSTING_RECORD_DOCUMENT_REFERENCE_TYPE_ORDERNUMBER) {
            $referenceNumber = $order->getOrderNumber();
        }

        return $referenceNumber;
    }

    private function getPostingText(OrderEntity $order): string
    {
        return sprintf('%s, %s', $order->getOrderNumber(), $order->getOrderCustomer()?->getCustomerNumber() ?? '');
    }

    private function getEuCountryAndVatId(AccountingDocumentRequestItem $item): ?string
    {
        $revenueAccountItem = $item->getAccountRequestItem();
        if (
            !$revenueAccountItem->getCountryIsoCode() || !in_array(
                $revenueAccountItem->getCountryIsoCode(),
                PickwareDatevBundle::ISO_CODES_OF_DESTINATION_COUNTRIES_OF_EUROPEAN_UNION_DELIVERIES,
                strict: true,
            )
        ) {
            return null;
        }
        if (!$revenueAccountItem->hasVatId()) {
            return $revenueAccountItem->getCountryIsoCode();
        }

        return $revenueAccountItem->getCustomerVatIds()[0];
    }

    private function getEuTaxRateForPrice(AccountingDocumentPriceItem $price, ?string $isoCodeOfDeliveryCountry): ?float
    {
        if (
            !$isoCodeOfDeliveryCountry || !in_array(
                $isoCodeOfDeliveryCountry,
                PickwareDatevBundle::ISO_CODES_OF_DESTINATION_COUNTRIES_OF_INTRA_COMMUNITY_DELIVERIES_FROM_GERMANY,
            )
        ) {
            return null;
        }

        return $price->getTaxRate();
    }
}
