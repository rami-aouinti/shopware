<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\InvoiceCorrection;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\InvoiceStack\InvoiceStack;
use Pickware\PickwareErpStarter\InvoiceStack\InvoiceStackService;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Framework\Context;

class InvoiceCorrectionConfigGenerator
{
    public const DOCUMENT_CONFIGURATION_REFERENCED_DOCUMENT_ID_KEY = 'pickwareErpReferencedDocumentId';
    public const DOCUMENT_CONFIGURATION_REFERENCED_INVOICE_NUMBER_KEY = 'pickwareErpReferencedInvoiceDocumentNumber';

    public function __construct(
        private readonly InvoiceStackService $invoiceStackService,
        private readonly InvoiceCorrectionCalculator $invoiceCorrectionCalculator,
        private readonly EntityManager $entityManager,
    ) {}

    /**
     * Determines the latest open invoice stack and returns the latest invoice correction or invoice id of that stack,
     * as well as the invoice number of that stack.
     *
     * REMARK REGARDING THE RETURN ORDER MVP: If there are multiple open invoice stacks (which is a valid scenario for
     * the MVP) the latest invoice stack that already has an invoice correction is used. If no invoice correction exists
     * in any open invoice stack, the invoice stack of the latest invoice document is used instead.
     *
     * Also: a storno document can only be created when there are no invoice corrections in the invoice stack. This
     * behavior will change in the future.
     */
    /**
     * @return array<string, string>
     */
    public function getReferencedDocumentConfiguration(string $orderId, Context $context): array
    {
        $invoiceStacks = $this->invoiceStackService->getInvoiceStacksOfOrder($orderId, $context);
        $openInvoiceStacks = $invoiceStacks->filter(fn(InvoiceStack $invoiceStack) => $invoiceStack->isOpen);

        if (count($openInvoiceStacks) === 0) {
            // No open invoice stack was found, no invoice correction can be created
            throw InvoiceCorrectionException::noReferenceDocumentFound();
        }

        $openInvoiceStacksWithInvoiceCorrections = $openInvoiceStacks->filter(
            fn(InvoiceStack $invoiceStack) => $invoiceStack->hasInvoiceCorrections(),
        );
        if (count($openInvoiceStacksWithInvoiceCorrections) > 0) {
            // Use the latest invoice stack and reference the latest invoice correction document of that stack
            $relevantInvoiceStack = $openInvoiceStacksWithInvoiceCorrections->getLatest();

            $this->validateNonEmptyInvoiceCorrection(
                $orderId,
                $relevantInvoiceStack->getLatestInvoiceCorrection()->id,
                $context->getVersionId(),
                $context,
            );

            return [
                self::DOCUMENT_CONFIGURATION_REFERENCED_DOCUMENT_ID_KEY => $relevantInvoiceStack->getLatestInvoiceCorrection()->id,
                self::DOCUMENT_CONFIGURATION_REFERENCED_INVOICE_NUMBER_KEY => $relevantInvoiceStack->invoice->number,
            ];
        }

        // If no invoice correction document exists in any open invoice stack, use the latest invoice stack instead
        // and reference the invoice document of that stack
        $relevantInvoiceStack = $openInvoiceStacks->getLatest();

        $this->validateNonEmptyInvoiceCorrection(
            $orderId,
            $relevantInvoiceStack->invoice->id,
            $context->getVersionId(),
            $context,
        );

        return [
            self::DOCUMENT_CONFIGURATION_REFERENCED_DOCUMENT_ID_KEY => $relevantInvoiceStack->invoice->id,
            self::DOCUMENT_CONFIGURATION_REFERENCED_INVOICE_NUMBER_KEY => $relevantInvoiceStack->invoice->number,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getReferencedDocumentConfigurationForExistingInvoiceCorrection(
        string $documentId,
        Context $context,
    ): array {
        /** @var DocumentEntity $document */
        $document = $this->entityManager->getByPrimaryKey(
            DocumentDefinition::class,
            $documentId,
            $context,
        );

        $referencedDocumentId = $document->getConfig()['custom'][self::DOCUMENT_CONFIGURATION_REFERENCED_DOCUMENT_ID_KEY] ?? null;
        $referencedInvoiceNumber = $document->getConfig()['custom'][self::DOCUMENT_CONFIGURATION_REFERENCED_INVOICE_NUMBER_KEY] ?? null;

        if ($referencedDocumentId === null || $referencedInvoiceNumber === null) {
            throw InvoiceCorrectionException::noInvoiceCorrectionReferenceInformationFound($documentId);
        }

        return [
            self::DOCUMENT_CONFIGURATION_REFERENCED_DOCUMENT_ID_KEY => $referencedDocumentId,
            self::DOCUMENT_CONFIGURATION_REFERENCED_INVOICE_NUMBER_KEY => $referencedInvoiceNumber,
        ];
    }

    private function validateNonEmptyInvoiceCorrection(
        string $orderId,
        string $referencedDocumentId,
        string $newVersionId,
        Context $context,
    ): void {
        $invoiceCorrection = $this->invoiceCorrectionCalculator->calculateInvoiceCorrection(
            $orderId,
            $referencedDocumentId,
            $newVersionId,
            $context,
        );

        if ($invoiceCorrection->isEmpty()) {
            throw InvoiceCorrectionException::invoiceCorrectionWouldBeEmpty();
        }
    }
}
