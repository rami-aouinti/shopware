<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\OrderDocument;

use Pickware\DocumentBundle\Renderer\DomPdfRenderer;
use function Pickware\ShopwareExtensionsBundle\VersionCheck\minimumShopwareVersion;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;

class OrderDocumentRenderer
{
    public function __construct(
        private readonly DomPdfRenderer $pdfRenderer,
    ) {}

    public function createRenderedDocument(
        string $number,
        string $name,
        string $fileExtension,
        array $config,
        string $html,
    ): RenderedDocument {
        if (minimumShopwareVersion('6.7')) {
            $renderedDocument = new RenderedDocument(
                number: $number,
                name: $name,
                fileExtension: $fileExtension,
                config: $config,
            );

            $renderedDocument->setContent($this->pdfRenderer->render($renderedDocument, $html));
        } else {
            $renderedDocument = new RenderedDocument(
                html: $html,
                number: $number,
                name: $name,
                fileExtension: $fileExtension,
                config: $config,
            );
        }

        return $renderedDocument;
    }
}
