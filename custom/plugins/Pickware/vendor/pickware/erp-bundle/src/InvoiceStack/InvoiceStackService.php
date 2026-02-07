<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\InvoiceStack;

use Exception;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\InvoiceCorrection\InvoiceCorrectionConfigGenerator;
use Pickware\PickwareErpStarter\InvoiceCorrection\InvoiceCorrectionDocumentType;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\StornoRenderer;
use Shopware\Core\Framework\Context;

class InvoiceStackService
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getInvoiceStacksOfOrder(string $orderId, Context $context): InvoiceStackCollection
    {
        /** @var DocumentCollection $documents */
        $documents = $this->entityManager->findBy(
            DocumentDefinition::class,
            ['orderId' => $orderId],
            $context,
            ['documentType'],
        );

        $invoiceStackCollection = new InvoiceStackCollection();
        $nonInvoiceDocumentEntities = [];
        foreach ($documents->getElements() as $documentEntity) {
            if ($documentEntity->getDocumentType()->getTechnicalName() === InvoiceRenderer::TYPE) {
                $invoiceStackCollection->add(new InvoiceStack($this->createInvoiceStackDocument($documentEntity)));
            } else {
                $nonInvoiceDocumentEntities[] = $documentEntity;
            }
        }

        foreach ($nonInvoiceDocumentEntities as $nonInvoiceDocumentEntity) {
            $type = $nonInvoiceDocumentEntity->getDocumentType()->getTechnicalName();
            if ($type === InvoiceCorrectionDocumentType::TECHNICAL_NAME) {
                $invoiceNumber = $nonInvoiceDocumentEntity->getConfig()['custom'][InvoiceCorrectionConfigGenerator::DOCUMENT_CONFIGURATION_REFERENCED_INVOICE_NUMBER_KEY];
                $invoiceCorrection = $this->createInvoiceStackDocument($nonInvoiceDocumentEntity);
                $invoiceStack = $invoiceStackCollection->getByInvoiceNumber($invoiceNumber);

                if ($invoiceStack) {
                    $invoiceStack->invoiceCorrections[] = $invoiceCorrection;
                }
            }
            if ($type === StornoRenderer::TYPE) {
                // If there is a storno document for an invoice, the respective invoice stack is considered "closed".
                $invoiceId = $nonInvoiceDocumentEntity->getReferencedDocumentId();
                $invoiceStack = $invoiceStackCollection->get($invoiceId);

                if ($invoiceStack) {
                    $invoiceStack->isOpen = false;
                }
            }
            // Documents of unsupported (or irrelevant) document types are ignored. They are not added to the invoice
            // stacks.
        }

        return $invoiceStackCollection;
    }

    private function createInvoiceStackDocument(DocumentEntity $documentEntity): InvoiceStackDocument
    {
        return new InvoiceStackDocument(
            $documentEntity->getId(),
            $this->getDocumentNumber($documentEntity),
            $documentEntity->getCreatedAt(),
        );
    }

    private function getDocumentNumber(DocumentEntity $documentEntity): string
    {
        if (!isset($documentEntity->getConfig()['documentNumber']) || $documentEntity->getConfig()['documentNumber'] === '') {
            throw new Exception(sprintf(
                'Document configuration is missing its "documentNumber". Document type: %s, id: %s.',
                $documentEntity->getDocumentType()->getTechnicalName(),
                $documentEntity->getId(),
            ));
        }

        return $documentEntity->getConfig()['documentNumber'];
    }
}
