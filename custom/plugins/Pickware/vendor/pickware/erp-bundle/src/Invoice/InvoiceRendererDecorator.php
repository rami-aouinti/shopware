<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Invoice;

use Pickware\PickwareErpStarter\Invoice\CheckDuplicate\CheckDuplicateInvoiceService;
use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\RendererResult;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

#[AsDecorator(InvoiceRenderer::class)]
class InvoiceRendererDecorator extends AbstractDocumentRenderer
{
    public function __construct(
        private readonly AbstractDocumentRenderer $innerRenderer,
        private readonly CheckDuplicateInvoiceService $checkDuplicateInvoiceService,
    ) {}

    public function supports(): string
    {
        return $this->innerRenderer->supports();
    }

    public function render(array $operations, Context $context, DocumentRendererConfig $rendererConfig): RendererResult
    {
        $checkDuplicateInvoiceResult = $this->checkDuplicateInvoiceService->filterOperationsWithDuplicateInvoices($operations, $context);
        $operationsWithDocumentIds = array_filter($operations, fn($operation) => $operation->getDocumentId() !== null);

        $results = $this->innerRenderer->render(
            [
                ...$operationsWithDocumentIds,
                ...$checkDuplicateInvoiceResult->operationWithoutOpenInvoices,
            ],
            $context,
            $rendererConfig,
        );

        foreach ($checkDuplicateInvoiceResult->operationWithOpenInvoices as $operation) {
            $results->addError(
                $operation->getOrderId(),
                InvoiceGenerationException::nonCancelledInvoiceAlreadyExistsError(),
            );
        }

        return $results;
    }

    public function getDecorated(): AbstractDocumentRenderer
    {
        return $this->innerRenderer;
    }
}
