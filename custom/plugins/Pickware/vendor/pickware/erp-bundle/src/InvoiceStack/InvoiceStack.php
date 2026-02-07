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

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class InvoiceStack
{
    /**
     * @var InvoiceStackDocument[]
     */
    public array $invoiceCorrections;

    public InvoiceStackDocument $invoice;
    public bool $isOpen = true;

    /**
     * @param InvoiceStackDocument[] $invoiceCorrections
     */
    public function __construct(InvoiceStackDocument $invoice, array $invoiceCorrections = [])
    {
        $this->invoice = $invoice;
        $this->invoiceCorrections = $invoiceCorrections;
    }

    public function hasInvoiceCorrections(): bool
    {
        return count($this->invoiceCorrections) > 0;
    }

    public function getLatestInvoiceCorrection(): ?InvoiceStackDocument
    {
        if (count($this->invoiceCorrections) === 0) {
            return null;
        }

        // Copy so we do not sort the actual invoice corrections or modify the pointer of the array
        $sortedInvoiceCorrections = $this->invoiceCorrections;
        usort(
            $sortedInvoiceCorrections,
            fn(InvoiceStackDocument $lhs, InvoiceStackDocument $rhs) => $lhs->createdAt <=> $rhs->createdAt,
        );

        return end($sortedInvoiceCorrections);
    }
}
