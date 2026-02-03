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

use Pickware\PickwareErpStarter\InvoiceStack\InvoiceStackService;
use Shopware\Core\Checkout\Document\Event\StornoOrdersEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StornoDocumentCreationValidator implements EventSubscriberInterface
{
    private InvoiceStackService $invoiceStackService;

    public function __construct(InvoiceStackService $invoiceStackService)
    {
        $this->invoiceStackService = $invoiceStackService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StornoOrdersEvent::class => 'validateNoInvoiceCorrectionForInvoiceExists',
        ];
    }

    public function validateNoInvoiceCorrectionForInvoiceExists(StornoOrdersEvent $event): void
    {
        foreach ($event->getOrders() as $order) {
            $operation = $event->getOperations()[$order->getId()];
            $customFields = $operation->getConfig()['custom'] ?? null;
            if ($customFields === null) {
                continue;
            }
            $invoiceStacks = $this->invoiceStackService->getInvoiceStacksOfOrder($order->getId(), $event->getContext());
            foreach ($invoiceStacks as $invoiceStack) {
                if ($invoiceStack->invoice->number === $customFields['invoiceNumber'] && $invoiceStack->hasInvoiceCorrections()) {
                    throw InvoiceCorrectionException::invoiceCorrectionForInvoiceExists($customFields['invoiceNumber']);
                }
            }
        }
    }
}
