<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\AccountingDocumentPicture\Guid;

use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\AccountingDocumentPicture\Guid\Model\AccountingDocumentGuidDefinition;
use Pickware\DatevBundle\AccountingDocumentPicture\Guid\Model\AccountingDocumentGuidEntity;
use Pickware\DatevBundle\AccountingDocumentPicture\Guid\Model\ImportExportAccountingDocumentGuidMappingDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class AccountingDocumentGuidService
{
    public function __construct(
        public readonly EntityManager $entityManager,
    ) {}

    public function ensureAccountingDocumentGuidExistsForDocumentId(string $documentId, string $exportId, Context $context): string
    {
        return $this->entityManager->runInTransactionWithRetry(function() use ($documentId, $exportId, $context): string {
            /** @var ?AccountingDocumentGuidEntity $documentReceiptGuid */
            $documentReceiptGuid = $this->entityManager->findOneBy(
                AccountingDocumentGuidDefinition::class,
                ['documentId' => $documentId],
                $context,
            );

            $receiptGuidId = $documentReceiptGuid?->getId();
            $receiptGuid = $documentReceiptGuid?->getGuid();

            if ($documentReceiptGuid === null) {
                $receiptGuidId = Uuid::randomHex();
                $receiptGuid = $this->generateGUID();

                $this->entityManager->create(
                    AccountingDocumentGuidDefinition::class,
                    [
                        [
                            'id' => $receiptGuidId,
                            'guid' => $receiptGuid,
                            'documentId' => $documentId,
                        ],
                    ],
                    $context,
                );
            }

            $this->entityManager->create(
                ImportExportAccountingDocumentGuidMappingDefinition::class,
                [
                    [
                        'accountingDocumentGuidId' => $receiptGuidId,
                        'importExportId' => $exportId,
                    ],
                ],
                $context,
            );

            return $this->formatGuidForExport($receiptGuid);
        });
    }

    private function formatGuidForExport(string $receiptGuid): string
    {
        return sprintf('BEDI ""%s""', $receiptGuid);
    }

    private function generateGUID(): string
    {
        return mb_strtoupper(sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 65535),
            random_int(0, 65535),
            random_int(0, 65535),
            random_int(16384, 20479),
            random_int(32768, 49151),
            random_int(0, 65535),
            random_int(0, 65535),
            random_int(0, 65535),
        ));
    }
}
