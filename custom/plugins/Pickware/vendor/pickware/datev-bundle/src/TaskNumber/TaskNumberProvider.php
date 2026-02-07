<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\TaskNumber;

use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\AccountingDocument\AccountingDocumentMessage;
use Pickware\DatevBundle\Config\Values\ConfigValues;
use Pickware\DatevBundle\Config\Values\PostingRecordTaskNumberType;
use Pickware\DatevBundle\Payment\PaymentMessage;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\Order\ExternalOrderNumberFieldSet;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class TaskNumberProvider
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public function getTaskNumberForDocument(
        string $orderId,
        string $orderVersionId,
        string $documentId,
        ConfigValues $datevConfig,
        Context $context,
    ): TaskNumberResult {
        $taskNumberData = $this->resolveTaskNumber($orderId, $orderVersionId, $datevConfig, $context);

        if (!$taskNumberData['taskNumber'] || $this->checkTaskNumberMaxLength($taskNumberData['taskNumber'])) {
            return new TaskNumberResult(
                $taskNumberData['taskNumber'] ?? $taskNumberData['orderNumber'],
                ImmutableCollection::create(),
            );
        }

        /** @var DocumentEntity $document */
        $document = $this->entityManager->getByPrimaryKey(
            DocumentDefinition::class,
            $documentId,
            $context,
            ['documentType'],
        );

        return new TaskNumberResult(
            $taskNumberData['orderNumber'],
            ImmutableCollection::create([
                AccountingDocumentMessage::createTaskNumberMaxLengthExceededWarning(
                    $taskNumberData['taskNumber'],
                    $taskNumberData['orderNumber'],
                    $document->getConfig()['documentNumber'] ?? null,
                    $document->getDocumentType()->getTechnicalName(),
                ),
            ]),
        );
    }

    public function getTaskNumberForPayment(
        string $orderId,
        string $orderVersionId,
        ConfigValues $datevConfig,
        Context $context,
    ): TaskNumberResult {
        $taskNumberData = $this->resolveTaskNumber($orderId, $orderVersionId, $datevConfig, $context);

        if ($taskNumberData['taskNumber'] === null || $this->checkTaskNumberMaxLength($taskNumberData['taskNumber'])) {
            return new TaskNumberResult(
                $taskNumberData['taskNumber'] ?? $taskNumberData['orderNumber'],
                ImmutableCollection::create(),
            );
        }

        return new TaskNumberResult(
            $taskNumberData['orderNumber'],
            ImmutableCollection::create([
                PaymentMessage::createTaskNumberMaxLengthExceededWarning(
                    $taskNumberData['taskNumber'],
                    $taskNumberData['orderNumber'],
                ),
            ]),
        );
    }

    /**
     * @return array{taskNumber: ?string, orderNumber: string}
     */
    private function resolveTaskNumber(
        string $orderId,
        string $orderVersionId,
        ConfigValues $datevConfig,
        Context $context,
    ): array {
        /** @var OrderEntity $order */
        $order = $this->entityManager->getOneBy(
            OrderDefinition::class,
            [
                'id' => $orderId,
            ],
            $context->createWithVersionId($orderVersionId),
        );

        $taskNumberSource = $datevConfig->getPostingRecord()['taskNumberSource'] ?? null;

        if ($taskNumberSource === null || $taskNumberSource === PostingRecordTaskNumberType::OrderNumber) {
            return [
                'taskNumber' => null,
                'orderNumber' => $order->getOrderNumber(),
            ];
        }

        $taskNumber = (string) $order->getCustomFieldsValue(ExternalOrderNumberFieldSet::TECHNICAL_NAME);
        if (
            $taskNumber === ''
            && $taskNumberSource === PostingRecordTaskNumberType::ExternalOrderNumberWithFallback
        ) {
            return [
                'taskNumber' => null,
                'orderNumber' => $order->getOrderNumber(),
            ];
        }

        return [
            'taskNumber' => $taskNumber,
            'orderNumber' => $order->getOrderNumber(),
        ];
    }

    private function checkTaskNumberMaxLength(string $taskNumber): bool
    {
        return mb_strlen($taskNumber) <= 30;
    }
}
