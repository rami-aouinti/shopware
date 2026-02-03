<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\OrderTaxValidation;

use Pickware\DalBundle\EntityManager;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class TaxInformationValidator
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public function areOrdersTaxInformationValid(array $orderIds, Context $context): bool
    {
        /** @var OrderCollection $orders */
        $orders = $this->entityManager->findBy(
            OrderDefinition::class,
            [
                'id' => $orderIds,
            ],
            $context,
        );

        foreach ($orders as $order) {
            if (!$this->isOrderTaxInformationComplete($order)) {
                return false;
            }
        }

        return true;
    }

    public function isOrderTaxInformationValid(string $orderId, Context $context): bool
    {
        return $this->areOrdersTaxInformationValid([$orderId], $context);
    }

    public function isDocumentTaxInformationValid(string $documentId, Context $context): bool
    {
        /** @var DocumentEntity $document */
        $document = $this->entityManager->getByPrimaryKey(
            DocumentDefinition::class,
            $documentId,
            $context,
            [
                'order',
                'documentType',
            ],
        );

        $documentTypeTechnicalName = $document->getDocumentType()?->getTechnicalName();
        if (!in_array($documentTypeTechnicalName, [InvoiceRenderer::TYPE, ZugferdRenderer::TYPE, ZugferdEmbeddedRenderer::TYPE], true)) {
            return true;
        }

        return $this->isOrderTaxInformationComplete($document->getOrder());
    }

    private function isOrderTaxInformationComplete(OrderEntity $order): bool
    {
        $isOrderTaxFree = $order->getPrice()->isTaxFree();
        $orderTotalPrice = $order->getPrice()->getTotalPrice();
        $orderCalculatedTaxes = $order->getPrice()->getCalculatedTaxes();

        // This especially avoids the situation where the order is not tax-free and has a total price,
        // but is missing calculated taxes.
        // See https://github.com/pickware/shopware-plugins/issues/8263
        return $isOrderTaxFree || $orderTotalPrice === 0.0 || !empty($orderCalculatedTaxes->getElements());
    }
}
