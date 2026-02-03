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

use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<InvoiceStack>
 * @method InvoiceStack[] getIterator()
 * @method InvoiceStack[] getElements()
 * @method InvoiceStack|null get(string $key)
 * @method InvoiceStack|null first()
 * @method InvoiceStack|null last()
 */
class InvoiceStackCollection extends Collection
{
    /**
     * @param InvoiceStack $element
     */
    public function add($element): void
    {
        $this->set($this->getKey($element), $element);
    }

    /**
     * @param string $key
     * @param InvoiceStack $element
     */
    public function set($key, $element): void
    {
        parent::set($this->getKey($element), $element);
    }

    public function getExpectedClass(): ?string
    {
        return InvoiceStack::class;
    }

    /**
     * Each element (InvoiceStack) of this collection is mapped by the invoice id of the InvoiceStack's invoice. Hence,
     * when using the ::get() function, an invoice document id must be used as argument.
     */
    private function getKey(InvoiceStack $invoiceStack): string
    {
        return $invoiceStack->invoice->id;
    }

    public function getByInvoiceNumber(string $documentNumber): ?InvoiceStack
    {
        /** @var InvoiceStack $invoiceStack */
        foreach ($this->elements as $invoiceStack) {
            if ($invoiceStack->invoice->number === $documentNumber) {
                return $invoiceStack;
            }
        }

        return null;
    }

    public function getLatest(): ?InvoiceStack
    {
        if (count($this->elements) === 0) {
            return null;
        }

        // Copy so we do not sort the actual elements of this collection
        $sorted = $this->elements;
        usort($sorted, fn(InvoiceStack $lhs, InvoiceStack $rhs) => $lhs->invoice->createdAt <=> $rhs->invoice->createdAt);

        return end($sorted);
    }

    public function containsOpenInvoiceStack(): bool
    {
        /** @var InvoiceStack $element */
        foreach ($this->elements as $element) {
            if ($element->isOpen) {
                return true;
            }
        }

        return false;
    }
}
