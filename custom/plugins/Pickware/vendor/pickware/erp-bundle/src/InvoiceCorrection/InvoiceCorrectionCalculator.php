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
use Pickware\PickwareErpStarter\InvoiceCorrection\Events\InvoiceCorrectionValidationEvent;
use Pickware\PickwareErpStarter\OrderCalculation\CalculatableOrder;
use Pickware\PickwareErpStarter\OrderCalculation\CalculatableOrderFactory;
use Pickware\PickwareErpStarter\OrderCalculation\OrderCalculationService;
use Pickware\PickwareErpStarter\OrderCalculation\OrderDifferenceCalculator;
use Pickware\PickwareErpStarter\OrderCalculation\PriceNegator;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;

class InvoiceCorrectionCalculator
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly OrderDifferenceCalculator $orderDifferenceCalculator,
        private readonly CalculatableOrderFactory $orderFactory,
        private readonly OrderCalculationService $orderCalculationService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * The content of an invoice correction is the calculated order difference between two versions of the same order.
     * The "old" order version is the version of the referenced document that is either an invoice or another invoice
     * correction. The "new" order version is the version of the invoice correction for which the content is calculated.
     *
     * If the referenced document is an invoice, the return orders of that document are not included in this calculation
     * (they are subtracted again after the order difference calculator subtracted them in its calculation). In other
     * words: the content of return orders is still part of the invoice correction even if the return orders are part of
     * both the referenced invoice and the newly created invoice correction.
     * Reason: If the user creates an order, then a return order, then creates the invoice document (after the return
     * order), the content of the return order is now already part of the invoice order version. When creation an
     * invoice correction document afterwards, the return order is now part of both the old and new order version and
     * the invoice correction would be empty. Re-subtracting these return orders of the invoice in this method fixes
     * this scenario.
     */
    public function calculateInvoiceCorrection(
        string $orderId,
        string $referencedDocumentId,
        string $invoiceCorrectionOrderVersionId,
        Context $context,
    ): CalculatableOrder {
        $validationEvent = new InvoiceCorrectionValidationEvent($orderId, $context);
        $this->eventDispatcher->dispatch($validationEvent);
        if ($validationEvent->hasJsonApiErrors()) {
            throw new InvoiceCorrectionException($validationEvent->getJsonApiErrors());
        }

        /** @var DocumentEntity $referencedDocument */
        $referencedDocument = $this->entityManager->getByPrimaryKey(
            DocumentDefinition::class,
            $referencedDocumentId,
            $context,
            [
                'documentType',
                'pickwareErpDocumentVersion',
            ],
        );

        // In Shopware there is a bug where the documents orderVersionId can be overwritten with the live version.
        // This causes our calculation to fail and as a workaround we save the orderVersionId into our own entity
        // when a new document is created with our InsertDocumentSubscriber. For old documents this is not the case
        // which is why we need to check if the document has our entity as an extension.
        //
        // See more here:
        // - https://github.com/pickware/shopware-plugins/issues/4709
        // - https://issues.shopware.com/issues/NEXT-29601
        $referencedDocumentOrderVersionId = $referencedDocument->getExtension('pickwareErpDocumentVersion') ? $referencedDocument->getExtension('pickwareErpDocumentVersion')->getOrderVersionId() : $referencedDocument->getOrderVersionId();

        try {
            $orderDifference = $this->orderDifferenceCalculator->calculateOrderDifference(
                $orderId,
                $referencedDocumentOrderVersionId,
                $invoiceCorrectionOrderVersionId,
                $context,
            );
        } catch (Throwable $e) {
            throw InvoiceCorrectionException::orderCalculationFailed(
                $orderId,
                $e->getMessage(),
            );
        }

        if ($referencedDocument->getDocumentType()->getTechnicalName() !== InvoiceRenderer::TYPE) {
            return $orderDifference;
        }

        $oldVersionContext = $context->createWithVersionId($referencedDocumentOrderVersionId);
        $oldReturnOrdersAsNegatedOrders = array_values(array_map(
            fn(CalculatableOrder $returnOrderAsOrder) => $returnOrderAsOrder->negated(new PriceNegator()),
            $this->orderFactory->createCalculatableOrdersFromReturnOrdersOfOrder($orderId, $oldVersionContext),
        ));

        return $this->orderCalculationService->mergeOrders(
            $orderDifference,
            ...$oldReturnOrdersAsNegatedOrders,
        );
    }
}
