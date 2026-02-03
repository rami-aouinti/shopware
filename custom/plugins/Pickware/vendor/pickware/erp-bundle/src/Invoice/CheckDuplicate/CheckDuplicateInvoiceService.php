<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Invoice\CheckDuplicate;

use Pickware\PickwareErpStarter\Invoice\PickwareInvoiceConfig;
use Pickware\PickwareErpStarter\InvoiceStack\InvoiceStackService;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;

class CheckDuplicateInvoiceService
{
    public function __construct(
        private readonly InvoiceStackService $invoiceStackService,
    ) {}

    /**
     * Checks the given operations for open invoices and excludes those that already reference an existing document.
     * Returns:
     * - operations with open invoices (or forced allow duplicates) that do not reference an existing document, and
     * - operations without open invoices
     *
     * @param DocumentGenerateOperation[] $operations
     */
    public function filterOperationsWithDuplicateInvoices(array $operations, Context $context): CheckDuplicateInvoiceResult
    {
        $operationsWithOpenInvoices = array_filter(
            $operations,
            function($operation, $orderId) use ($context) {
                if ($operation->getExtensionOfType(PickwareInvoiceConfig::EXTENSION_KEY, PickwareInvoiceConfig::class)?->allowDuplicates) {
                    return false;
                }

                return $this->invoiceStackService->getInvoiceStacksOfOrder($orderId, $context)->containsOpenInvoiceStack();
            },
            ARRAY_FILTER_USE_BOTH,
        );
        $operationsWithoutOpenInvoices = array_diff_key($operations, $operationsWithOpenInvoices);

        $operationsWithOpenInvoices = array_filter(
            $operationsWithOpenInvoices,
            fn(DocumentGenerateOperation $operation) => $operation->getDocumentId() === null,
        );

        return new CheckDuplicateInvoiceResult(
            $operationsWithOpenInvoices,
            $operationsWithoutOpenInvoices,
        );
    }
}
