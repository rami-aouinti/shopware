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

use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\AccountingDocument\AccountingDocumentRecordCreator;
use Pickware\DatevBundle\PickwareDatevBundle;
use Pickware\PickwareErpStarter\InvoiceCorrection\InvoiceCorrectionDocumentType;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Framework\Context;

class PosAccountingDocumentClassifier
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    /**
     * Determine if a document can be exported by @see AccountingDocumentRecordCreator or needs to be left for the
     * @see PosAccountingDocumentRecordCreator
     */
    public function canDocumentBeExportedByDefaultExport(string $documentId, Context $context): bool
    {
        /** @var DocumentEntity $document */
        $document = $this->entityManager->findByPrimaryKey(
            DocumentDefinition::class,
            $documentId,
            $context,
            [
                'order.documents.documentType',
                'documentType',
            ],
        );

        if ($document === null) {
            return false;
        }

        $posReceiptDocumentCount = $document
            ->getOrder()
            ->getDocuments()
            ->filter(
                fn(DocumentEntity $document) =>
                    $document->getDocumentType()->getTechnicalName() === PickwareDatevBundle::PICKWARE_POS_RECEIPT_DOCUMENT_TYPE_TECHNICAL_NAME,
            )
            ->count();

        return $posReceiptDocumentCount === 0
            || $document->getDocumentType()->getTechnicalName() === PickwareDatevBundle::PICKWARE_POS_RECEIPT_DOCUMENT_TYPE_TECHNICAL_NAME
            || $document->getDocumentType()->getTechnicalName() === InvoiceCorrectionDocumentType::TECHNICAL_NAME;
    }
}
