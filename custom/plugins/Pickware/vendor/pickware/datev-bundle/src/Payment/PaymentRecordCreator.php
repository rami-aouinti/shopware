<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Payment;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use LogicException;
use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\Config\AccountAssignment\AccountAssignmentCustomer;
use Pickware\DatevBundle\Config\AccountAssignment\AccountAssignmentMetadata;
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
use Pickware\DatevBundle\TaskNumber\TaskNumberProvider;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\ShopwareExtensionsBundle\OrderTransaction\OrderTransactionCollectionExtension;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: EntryBatchRecordCreatorRegistry::DI_CONTAINER_TAG)]
class PaymentRecordCreator implements EntryBatchRecordCreator
{
    private const DOCUMENT_INFO_TYPE_SALES_CHANNEL = 'Verkaufskanal';
    private const DOCUMENT_INFO_TYPE_EXPORT_COMMENT = 'Exportkommentar';
    private const DOCUMENT_INFO_TYPE_TRANSACTION_REFERENCE = 'Referenz';
    private const ADDITIONAL_INFORMATION_TYPE_COMPANY = 'Firma';
    private const ADDITIONAL_INFORMATION_TYPE_TITLE = 'Titel';
    private const ADDITIONAL_INFORMATION_TYPE_FIRSTNAME = 'Vorname';
    private const ADDITIONAL_INFORMATION_TYPE_LASTNAME = 'Nachname';
    public const TECHNICAL_NAME = 'payment';

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Connection $db,
        private readonly ConfigService $configService,
        private readonly AccountRuleStackCreationService $accountRuleStackCreationService,
        private readonly ExportedIndividualDebtorService $exportedIndividualDebtorService,
        private readonly TaskNumberProvider $taskNumberProvider,
    ) {}

    /**
     * @param string[] $entityIds Ids of payment captures to be exported as returned by this domains
     * {@link PaymentEntityIdChunkCalculator}
     */
    public function createEntryBatchRecords(array $entityIds, array $exportConfig, string $exportId, Context $context): EntryBatchRecordCollection
    {
        if (count($entityIds) === 0) {
            return new EntryBatchRecordCollection();
        }

        $salesChannelIds = $this->db->fetchAllAssociative(
            <<<SQL
                SELECT DISTINCT LOWER(HEX(`order`.`sales_channel_id`)) AS `salesChannelId`
                FROM `pickware_datev_payment_capture`
                JOIN `order` ON `pickware_datev_payment_capture`.`order_id` = `order`.`id`
                    AND `pickware_datev_payment_capture`.`order_version_id` = `order`.`version_id`
                WHERE `pickware_datev_payment_capture`.`id` IN (:entityIds);
                SQL,
            ['entityIds' => array_map('hex2bin', $entityIds)],
            ['entityIds' => ArrayParameterType::BINARY],
        );
        if (count($salesChannelIds) > 1) {
            throw new LogicException('Export for more than one sales channel is not supported at the moment.');
        }
        $salesChannelId = $salesChannelIds[0]['salesChannelId'];

        $datevConfig = $this->configService->getConfig($salesChannelId, $context)->getValues();

        /** @var PaymentCaptureCollection $paymentCaptures */
        $paymentCaptures = $this->entityManager->findBy(
            PaymentCaptureDefinition::class,
            (new Criteria($entityIds))->addSorting(new FieldSorting('transactionDate')),
            $context,
            [
                'order.orderCustomer.customer',
                'order.transactions.stateMachineState',
                'order.salesChannel',
                'currency',
            ],
        );

        /** @var ImmutableCollection<PaymentRequestItem> $paymentRequestItems */
        $items = ImmutableCollection::create(array_map(
            fn(PaymentCaptureEntity $paymentCapture) => new PaymentRequestItem(
                $paymentCapture->getAmount(),
                $paymentCapture->getOrder(),
                $paymentCapture->getId(),
                new ClearingAccountRequestItem(
                    key: $paymentCapture->getOrderId(),
                    paymentMethodId: $this->getPaymentMethodId($paymentCapture->getOrder()),
                ),
                new DebtorAccountRequestItem(
                    key: $paymentCapture->getOrderId(),
                    paymentMethodId: $this->getPaymentMethodId($paymentCapture->getOrder()),
                ),
            ),
            $paymentCaptures->getElements(),
        ));

        $entryCollection = new EntryBatchRecordCollection();
        $exportedIndividualDebtorMaps = [];
        /** @var PaymentRequestItem $item */
        foreach ($items as $item) {
            $order = $item->getOrder();

            $amount = $item->getAmount();
            $debitCreditIdentifier = EntryBatchRecord::DEBIT_IDENTIFIER;
            if ($amount < 0) {
                $amount *= -1;
                $debitCreditIdentifier = EntryBatchRecord::CREDIT_IDENTIFIER;
            }

            /** @var PaymentMessage[] $failuresForPayment */
            $failuresForPayment = [];
            $accountRuleStack = $this->accountRuleStackCreationService->createClearingAccountRuleStack($datevConfig);
            $customer = $order->getOrderCustomer()?->getCustomer();
            $contraAccountRuleStack = $this->accountRuleStackCreationService->createDebtorAccountsRuleStack(
                $datevConfig,
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
            $accountAssignment = $accountAssignments->getByItem($item->getAccountRequestItem());
            $contraAccountAssignment = $contraAccountAssignments->getByItem($item->getContraAccountRequestItem());

            $exportedIndividualDebtorMaps[] = $this->exportedIndividualDebtorService->getIndividualDebtorAccountInformationMap(
                $contraAccountAssignments->getAsImmutableCollection(),
                $customer?->getId(),
            );

            $entryCollection->addLogMessages(...$accountAssignment->getMessages());
            $entryCollection->addLogMessages(...$contraAccountAssignment->getMessages());

            if ($accountAssignment->getAccountDetermination()->getAccount() === null) {
                $failuresForPayment[] = PaymentMessage::createAccountUnresolvedError($order->getOrderNumber());
            }

            if ($contraAccountAssignment->getAccountDetermination()->getAccount() === null) {
                $failuresForPayment[] = PaymentMessage::createContraAccountUnresolvedError($order->getOrderNumber());
            }

            if (count($failuresForPayment) > 0) {
                $entryCollection->addLogMessages(...$failuresForPayment);

                continue;
            }

            /** @var PaymentCaptureEntity $paymentCapture */
            $paymentCapture = $paymentCaptures->get($item->getPaymentCaptureId());

            $taskNumberResult = $this->taskNumberProvider->getTaskNumberForPayment(
                $order->getId(),
                $order->getVersionId(),
                $datevConfig,
                $context,
            );
            $entryCollection->addLogMessages(...$taskNumberResult->logMessages);

            $entryCollection->addEntries(new EntryBatchRecord(
                revenue: $amount,
                debitCreditIdentifier: $debitCreditIdentifier,
                account: $accountAssignment->getAccountDetermination()->getAccount(),
                contraAccount: $contraAccountAssignment->getAccountDetermination()->getAccount(),
                documentDate: $paymentCapture->getTransactionDate(),
                documentField1: $this->getReferenceNumberForOrder($paymentCapture, $datevConfig),
                postingText: $this->getPostingTextForOrder($order),
                receiptLink: null,
                documentInfoType1: self::DOCUMENT_INFO_TYPE_SALES_CHANNEL,
                documentInfoContent1: $order->getSalesChannel()->getName(),
                documentInfoType2: $paymentCapture->getExportComment() !== null ? self::DOCUMENT_INFO_TYPE_EXPORT_COMMENT : null,
                documentInfoContent2: $paymentCapture->getExportComment(),
                documentInfoType3: null,
                documentInfoContent3: null,
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
                taskNumber: $taskNumberResult->taskNumber,
            ));
        }

        $this->exportedIndividualDebtorService->ensureIndividualDebtorAccountInformationExistsForAccountsAndExport(
            $exportedIndividualDebtorMaps,
            $exportId,
            $context,
        );

        return $entryCollection;
    }

    public function getTechnicalName(): string
    {
        return self::TECHNICAL_NAME;
    }

    public function validateConfig(array $config): JsonApiErrors
    {
        return JsonApiErrors::noError();
    }

    private function getReferenceNumberForOrder(PaymentCaptureEntity $paymentCapture, ConfigValues $datevConfig): ?string
    {
        if ($datevConfig->getPostingRecord()['documentReference'] === ConfigValues::POSTING_RECORD_DOCUMENT_REFERENCE_TYPE_ORDERNUMBER) {
            return $paymentCapture->getOrder()->getOrderNumber();
        }

        return null;
    }

    private function getPostingTextForOrder(OrderEntity $order): string
    {
        return implode(', ', array_filter([
            $order->getOrderNumber(),
            $order->getOrderCustomer()?->getCustomerNumber(),
        ]));
    }

    private function getPaymentMethodId(OrderEntity $order): ?string
    {
        return OrderTransactionCollectionExtension::getPrimaryOrderTransaction($order->getTransactions())->getPaymentMethodId();
    }
}
