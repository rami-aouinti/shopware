<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PosPayment;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use LogicException;
use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\Config\AccountAssignment\AccountAssignmentCustomer;
use Pickware\DatevBundle\Config\AccountAssignment\AccountAssignmentMetadata;
use Pickware\DatevBundle\Config\AccountAssignment\Item\CashMovementRequestItem;
use Pickware\DatevBundle\Config\AccountAssignment\Item\ClearingAccountRequestItem;
use Pickware\DatevBundle\Config\AccountAssignment\Item\DebtorAccountRequestItem;
use Pickware\DatevBundle\Config\AccountRuleStackCreationService;
use Pickware\DatevBundle\Config\ConfigService;
use Pickware\DatevBundle\Config\Values\ConfigValues;
use Pickware\DatevBundle\EntryBatch\DependencyInjection\EntryBatchRecordCreatorRegistry;
use Pickware\DatevBundle\EntryBatch\EntryBatchRecord;
use Pickware\DatevBundle\EntryBatch\EntryBatchRecordCollection;
use Pickware\DatevBundle\EntryBatch\EntryBatchRecordCreator;
use Pickware\DatevBundle\IndividualDebtorAccountInformation\ExportedIndividualDebtorService;
use Pickware\DatevBundle\PaymentCapture\Model\PaymentCaptureCollection;
use Pickware\DatevBundle\PaymentCapture\Model\PaymentCaptureDefinition;
use Pickware\DatevBundle\PaymentCapture\Model\PaymentCaptureEntity;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwarePos\BranchStore\BranchStoreNameProvider;
use Pickware\PickwarePos\CashMovement\CashMovement;
use Pickware\PickwarePos\CashMovement\CashMovementProvider;
use Pickware\PickwarePos\CashMovement\CashMovementType;
use Pickware\ShopwareExtensionsBundle\OrderTransaction\OrderTransactionCollectionExtension;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: EntryBatchRecordCreatorRegistry::DI_CONTAINER_TAG)]
class PosPaymentRecordCreator implements EntryBatchRecordCreator
{
    private const DOCUMENT_INFO_TYPE_SALES_CHANNEL = 'Verkaufskanal';
    private const DOCUMENT_INFO_TYPE_EXPORT_COMMENT = 'Exportkommentar';
    private const DOCUMENT_INFO_TYPE_BRANCH_STORE = 'Filiale';
    private const DOCUMENT_INFO_TYPE_TRANSACTION_REFERENCE = 'Referenz';
    private const ADDITIONAL_INFORMATION_TYPE_COMPANY = 'Firma';
    private const ADDITIONAL_INFORMATION_TYPE_TITLE = 'Titel';
    private const ADDITIONAL_INFORMATION_TYPE_FIRSTNAME = 'Vorname';
    private const ADDITIONAL_INFORMATION_TYPE_LASTNAME = 'Nachname';
    public const TECHNICAL_NAME = 'pos-payment';

    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManager $entityManager,
        private readonly ConfigService $configService,
        private readonly AccountRuleStackCreationService $accountRuleStackCreationService,
        private readonly ?CashMovementProvider $cashMovementProvider,
        private readonly ?BranchStoreNameProvider $branchStoreNameProvider,
        private readonly ExportedIndividualDebtorService $exportedIndividualDebtorService,
    ) {}

    /**
     * @param string[] $entityIds Ids of {@link PaymentCaptureEntity}s to be exported as returned by this domains
     * {@link PosPaymentEntityIdChunkCalculator}.
     */
    public function createEntryBatchRecords(array $entityIds, array $exportConfig, string $exportId, Context $context): EntryBatchRecordCollection
    {
        if (count($entityIds) === 0) {
            return new EntryBatchRecordCollection();
        }

        $paymentExportConfig = PosPaymentExportConfig::fromExportConfig($exportConfig);

        $cashMovements = [];
        $cashMovementSalesChannelIds = [];
        if ($paymentExportConfig->usePosDataModelAbstraction) {
            $cashMovements = $this->cashMovementProvider->getCashMovements($entityIds, $context);
            $cashMovementSalesChannelIds = array_map(
                fn(CashMovement $cashMovement) => $cashMovement->getSalesChannelId(),
                $cashMovements,
            );
        }

        $orderSalesChannelIds = $this->connection->fetchFirstColumn(
            <<<SQL
                SELECT DISTINCT LOWER(HEX(`order`.`sales_channel_id`))
                FROM `pickware_datev_payment_capture`
                JOIN `order` ON `pickware_datev_payment_capture`.`order_id` = `order`.`id`
                    AND `pickware_datev_payment_capture`.`order_version_id` = `order`.`version_id`
                WHERE `pickware_datev_payment_capture`.`id` IN (:entityIds);
                SQL,
            ['entityIds' => array_map('hex2bin', $entityIds)],
            ['entityIds' => ArrayParameterType::BINARY],
        );
        $salesChannelIds = array_unique(array_merge(array_values($cashMovementSalesChannelIds), $orderSalesChannelIds));
        if (count($salesChannelIds) > 1) {
            throw new LogicException('Export for more than one sales channel is not supported at the moment.');
        }
        $salesChannelId = $salesChannelIds[0] ?? null;
        $config = $this->configService->getConfig($salesChannelId, $context)->getValues();

        $paymentCaptureRecordCollection = $this->createEntryBatchRecordsForPaymentCaptures(
            $config,
            $paymentExportConfig,
            $entityIds,
            $exportId,
            $context,
        );

        if (count($cashMovements) !== 0) {
            $cashMovementRecordCollection = $this->createEntryBatchRecordsForCashMovements(
                $salesChannelId,
                $config,
                $paymentExportConfig,
                $cashMovements,
                $context,
            );
            $paymentCaptureRecordCollection->mergeWith($cashMovementRecordCollection);
        }

        return $paymentCaptureRecordCollection;
    }

    /**
     * @param string[] $paymentCaptureIds
     */
    private function createEntryBatchRecordsForPaymentCaptures(
        ConfigValues $config,
        PosPaymentExportConfig $exportConfig,
        array $paymentCaptureIds,
        string $exportId,
        Context $context,
    ): EntryBatchRecordCollection {
        /** @var PaymentCaptureCollection $paymentCaptures */
        $paymentCaptures = $this->entityManager->findBy(
            PaymentCaptureDefinition::class,
            (new Criteria($paymentCaptureIds))->addSorting(new FieldSorting('transactionDate')),
            $context,
            [
                'order.transactions.stateMachineState',
                'order.orderCustomer.customer',
                'order.salesChannel',
                'returnOrderRefund',
                'currency',
            ],
        );

        /** @var ImmutableCollection<PosPaymentCaptureRequestItem> $paymentRequestItems */
        $items = ImmutableCollection::create(array_map(
            fn(PaymentCaptureEntity $paymentCapture) => new PosPaymentCaptureRequestItem(
                $paymentCapture->getAmount(),
                $paymentCapture->getOrder(),
                $paymentCapture->getId(),
                new ClearingAccountRequestItem(
                    key: $paymentCapture->getOrderId(),
                    paymentMethodId: $this->getPaymentMethodId($paymentCapture),
                ),
                new DebtorAccountRequestItem(
                    key: $paymentCapture->getOrderId(),
                    paymentMethodId: $this->getPaymentMethodId($paymentCapture),
                ),
            ),
            $paymentCaptures->getElements(),
        ));

        $branchStoreNamesByOrderId = [];
        if ($exportConfig->usePosDataModelAbstraction) {
            $orderIds = $paymentCaptures->map(fn(PaymentCaptureEntity $paymentCapture) => $paymentCapture->getOrderId());
            $branchStoreNamesByOrderId = $this->branchStoreNameProvider->getBranchStoreNamesByOrderId($orderIds, $context);
        }

        $entryCollection = new EntryBatchRecordCollection();
        $exportedIndividualDebtorMaps = [];
        /** @var PosPaymentCaptureRequestItem $item */
        foreach ($items as $item) {
            /** @var PaymentCaptureEntity $paymentCapture */
            $paymentCapture = $paymentCaptures->get($item->getPaymentCaptureId());
            $order = $item->getOrder();

            $amount = $item->getAmount();
            $debitCreditIdentifier = EntryBatchRecord::DEBIT_IDENTIFIER;
            if ($amount < 0) {
                $amount *= -1;
                $debitCreditIdentifier = EntryBatchRecord::DEBIT_CREDIT_INVERSION_MAPPING[$debitCreditIdentifier];
            }

            $accountRuleStack = $this->accountRuleStackCreationService->createClearingAccountRuleStack($config);
            $customer = $order->getOrderCustomer()?->getCustomer();
            $contraAccountRuleStack = $this->accountRuleStackCreationService->createDebtorAccountsRuleStack(
                $config,
                AccountAssignmentCustomer::fromRawCustomerData(
                    $customer?->getCustomerNumber(),
                    $customer?->getCustomFields(),
                ),
            );

            $accountAssignmentMetadata = new AccountAssignmentMetadata(
                documentType: 'payment',
                documentNumber: 'payment',
                orderNumber: $order->getOrderNumber() ?? 'unknown_order_number',
            );
            $accountAssignments = $accountRuleStack->map(
                new ImmutableCollection([$item->getAccountRequestItem()]),
                $accountAssignmentMetadata,
            );
            $contraAccountAssignments = $contraAccountRuleStack->map(
                new ImmutableCollection([$item->getContraAccountRequestItem()]),
                $accountAssignmentMetadata,
            );

            $exportedIndividualDebtorMaps[] = $this->exportedIndividualDebtorService->getIndividualDebtorAccountInformationMap(
                $contraAccountAssignments->getAsImmutableCollection(),
                $customer?->getId(),
            );

            $accountAssignment = $accountAssignments->getByItem($item->getAccountRequestItem());
            $contraAccountAssignment = $contraAccountAssignments->getByItem($item->getContraAccountRequestItem());
            $entryCollection->addLogMessages(...$accountAssignment->getMessages());
            $entryCollection->addLogMessages(...$contraAccountAssignment->getMessages());

            /** @var PosPaymentMessage[] $failuresForPayment */
            $failuresForPayment = [];
            if ($accountAssignment->getAccountDetermination()->getAccount() === null) {
                $failuresForPayment[] = PosPaymentMessage::createAccountUnresolvedForPaymentMessage(
                    $order->getOrderNumber(),
                    $item->getAmount(),
                    $paymentCapture->getCurrency()->getIsoCode(),
                );
            }

            if ($contraAccountAssignment->getAccountDetermination()->getAccount() === null) {
                $failuresForPayment[] = PosPaymentMessage::createContraAccountUnresolvedForPaymentMessage(
                    $order->getOrderNumber(),
                    $item->getAmount(),
                    $paymentCapture->getCurrency()->getIsoCode(),
                );
            }

            if (count($failuresForPayment) > 0) {
                $entryCollection->addLogMessages(...$failuresForPayment);

                continue;
            }

            $entryCollection->addEntries(new EntryBatchRecord(
                revenue: $amount,
                debitCreditIdentifier: $debitCreditIdentifier,
                account: $accountAssignment->getAccountDetermination()->getAccount(),
                contraAccount: $contraAccountAssignment->getAccountDetermination()->getAccount(),
                documentDate: $paymentCapture->getTransactionDate(),
                documentField1: $this->getReferenceNumberForOrder($paymentCapture, $config),
                postingText: $this->getPostingText($item),
                receiptLink: null,
                documentInfoType1: self::DOCUMENT_INFO_TYPE_SALES_CHANNEL,
                documentInfoContent1: $order->getSalesChannel()->getName(),
                documentInfoType2: $paymentCapture->getExportComment() !== null ? self::DOCUMENT_INFO_TYPE_EXPORT_COMMENT : null,
                documentInfoContent2: $paymentCapture->getExportComment(),
                documentInfoType3: self::DOCUMENT_INFO_TYPE_BRANCH_STORE,
                documentInfoContent3: $branchStoreNamesByOrderId[$paymentCapture->getOrderId()] ?? null,
                documentInfoType4: $paymentCapture->getTransactionReference() !== null ? self::DOCUMENT_INFO_TYPE_TRANSACTION_REFERENCE : null,
                documentInfoContent4: $paymentCapture->getTransactionReference(),
                costCenter1: null,
                costCenter2: null,
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
            ));
        }

        $this->exportedIndividualDebtorService->ensureIndividualDebtorAccountInformationExistsForAccountsAndExport(
            $exportedIndividualDebtorMaps,
            $exportId,
            $context,
        );

        return $entryCollection;
    }

    /**
     * @param CashMovement[] $cashMovements
     */
    private function createEntryBatchRecordsForCashMovements(
        string $salesChannelId,
        ConfigValues $config,
        PosPaymentExportConfig $exportConfig,
        array $cashMovements,
        Context $context,
    ): EntryBatchRecordCollection {
        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $this->entityManager->getByPrimaryKey(SalesChannelDefinition::class, $salesChannelId, $context);

        $currencyIds = array_map(fn(CashMovement $cashMovement) => $cashMovement->getCurrencyId(), $cashMovements);
        /** @var CurrencyCollection $currencies */
        $currencies = $this->entityManager->findBy(CurrencyDefinition::class, ['id' => $currencyIds], $context);

        $accountRuleStack = $this->accountRuleStackCreationService->createCashMovementAccountRuleStack($config);
        $contraAccountRuleStack = $this->accountRuleStackCreationService->createCashMovementContraAccountRuleStack($config);

        /** @var ImmutableCollection<PosPaymentCaptureRequestItem> $paymentRequestItems */
        $items = ImmutableCollection::create(array_map(
            fn(CashMovement $cashMovement) => new PosCashMovementRequestItem(
                cashMovement: $cashMovement,
                accountRequestItem: new CashMovementRequestItem(key: $cashMovement->getUniqueIdentifier()),
                contraAccountRequestItem: new CashMovementRequestItem(key: $cashMovement->getUniqueIdentifier()),
            ),
            $cashMovements,
        ));

        $accountAssignmentMetadata = new AccountAssignmentMetadata(
            documentType: 'pos_payment',
            documentNumber: 'pos_payment',
            orderNumber: 'pos_payment',
        );
        $accountAssignments = $accountRuleStack->map(
            $items->map(fn(PosCashMovementRequestItem $item) => $item->getAccountRequestItem()),
            $accountAssignmentMetadata,
        );
        $contraAccountAssignments = $contraAccountRuleStack->map(
            $items->map(fn(PosCashMovementRequestItem $item) => $item->getContraAccountRequestItem()),
            $accountAssignmentMetadata,
        );

        $entryCollection = new EntryBatchRecordCollection();
        /** @var PosCashMovementRequestItem $item */
        foreach ($items as $item) {
            $debitCreditIdentifier = match ($item->getCashMovement()->getType()) {
                CashMovementType::Deposit => EntryBatchRecord::DEBIT_IDENTIFIER,
                CashMovementType::Withdrawal => EntryBatchRecord::CREDIT_IDENTIFIER,
            };

            $accountAssignment = $accountAssignments->getByItem($item->getAccountRequestItem());
            $contraAccountAssignment = $contraAccountAssignments->getByItem($item->getContraAccountRequestItem());
            $entryCollection->addLogMessages(...$accountAssignment->getMessages());
            $entryCollection->addLogMessages(...$contraAccountAssignment->getMessages());

            /** @var PosPaymentMessage[] $failuresForCashMovement */
            $failuresForCashMovement = [];
            if ($accountAssignment->getAccountDetermination()->getAccount() === null) {
                $failuresForCashMovement[] = PosPaymentMessage::createAccountUnresolvedForCashMovementMessage(
                    $item->getCashMovement()->getBranchStoreName(),
                    $item->getCashMovement()->getCashRegisterName(),
                    $item->getCashMovement()->getAmount(),
                    $currencies->get($item->getCashMovement()->getCurrencyId())->getIsoCode(),
                );
            }

            if ($contraAccountAssignment->getAccountDetermination()->getAccount() === null) {
                $failuresForCashMovement[] = PosPaymentMessage::createContraAccountUnresolvedForCashMovementMessage(
                    $item->getCashMovement()->getBranchStoreName(),
                    $item->getCashMovement()->getCashRegisterName(),
                    $item->getCashMovement()->getAmount(),
                    $currencies->get($item->getCashMovement()->getCurrencyId())->getIsoCode(),
                );
            }

            if (count($failuresForCashMovement) > 0) {
                $entryCollection->addLogMessages(...$failuresForCashMovement);

                continue;
            }

            $entryCollection->addEntries(new EntryBatchRecord(
                revenue: abs($item->getCashMovement()->getAmount()),
                debitCreditIdentifier: $debitCreditIdentifier,
                account: $accountAssignment->getAccountDetermination()->getAccount(),
                contraAccount: $contraAccountAssignment->getAccountDetermination()->getAccount(),
                documentDate: $item->getCashMovement()->getDate(),
                documentField1: null,
                postingText: $item->getCashMovement()->getComment(),
                receiptLink: null,
                documentInfoType1: self::DOCUMENT_INFO_TYPE_SALES_CHANNEL,
                documentInfoContent1: $salesChannel->getName(),
                documentInfoType2: null,
                documentInfoContent2: null,
                documentInfoType3: self::DOCUMENT_INFO_TYPE_BRANCH_STORE,
                // For consistency reasons, we only export the branch store name if it will be exported for the payment
                // captures as well, e.g. when the POS data model abstraction can be used.
                documentInfoContent3: $exportConfig->usePosDataModelAbstraction ? $item->getCashMovement()->getBranchStoreName() : null,
                documentInfoType4: null,
                documentInfoContent4: null,
                costCenter1: null,
                costCenter2: null,
                euCountryAndVatId: null,
                euTaxRate: null,
                additionalInformationType1: null,
                additionalInformationContent1: null,
                additionalInformationType2: null,
                additionalInformationContent2: null,
                additionalInformationType3: null,
                additionalInformationContent3: null,
                additionalInformationType4: null,
                additionalInformationContent4: null,
                fixation: false,
                taskNumber: null,
            ));
        }

        return $entryCollection;
    }

    public function getTechnicalName(): string
    {
        return self::TECHNICAL_NAME;
    }

    public function validateConfig(array $config): JsonApiErrors
    {
        return PosPaymentExportConfig::validate($config);
    }

    private function getReferenceNumberForOrder(PaymentCaptureEntity $paymentCapture, ConfigValues $config): ?string
    {
        if ($config->getPostingRecord()['documentReference'] === ConfigValues::POSTING_RECORD_DOCUMENT_REFERENCE_TYPE_ORDERNUMBER) {
            return $paymentCapture->getOrder()->getOrderNumber();
        }

        return null;
    }

    private function getPostingText(PosPaymentCaptureRequestItem $item): string
    {
        return implode(', ', array_filter([
            $item->getOrder()->getOrderNumber(),
            $item->getOrder()->getOrderCustomer()?->getCustomerNumber(),
        ]));
    }

    private function getPaymentMethodId(PaymentCaptureEntity $paymentCapture): ?string
    {
        if ($paymentCapture->getReturnOrderRefundId() !== null) {
            return $paymentCapture->getReturnOrderRefund()->getPaymentMethodId();
        }

        return OrderTransactionCollectionExtension::getPrimaryOrderTransaction($paymentCapture->getOrder()->getTransactions())
            ->getPaymentMethodId();
    }
}
