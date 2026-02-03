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

use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\RendererResult;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

#[AsDecorator(ZugferdEmbeddedRenderer::class)]
class AllowEmbeddedZugferdInvoicesRendererDecorator extends AbstractDocumentRenderer
{
    public function __construct(
        private readonly AbstractDocumentRenderer $innerRenderer,
    ) {}

    public function supports(): string
    {
        return $this->innerRenderer->supports();
    }

    /**
     * We want to allow the creation of Embedded Zugferd invoices even if there is already an invoice for the same
     * order. The renderer uses internally the normal invoice renderer to generate the invoice and then embeds the
     * Zugferd XML into the invoice; therefore, we need to add an option to allow duplicates for invoices in that case.
     */
    public function render(array $operations, Context $context, DocumentRendererConfig $rendererConfig): RendererResult
    {
        /** @var DocumentGenerateOperation $operation */
        foreach ($operations as $operation) {
            $operation->addExtension(
                PickwareInvoiceConfig::EXTENSION_KEY,
                new PickwareInvoiceConfig(
                    allowDuplicates: true,
                ),
            );
        }

        return $this->innerRenderer->render($operations, $context, $rendererConfig);
    }

    public function getDecorated(): AbstractDocumentRenderer
    {
        return $this->innerRenderer;
    }
}
