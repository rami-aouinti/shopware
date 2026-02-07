<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\ShippingProcess;

use DateTimeImmutable;
use Pickware\DocumentBundle\Renderer\DocumentTemplateRenderer;
use Pickware\DocumentBundle\Renderer\DomPdfRenderer;
use Pickware\PickwareErpStarter\Translation\Translator;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Framework\Context;

class ShippingProcessReceiptDocumentGenerator
{
    public const DOCUMENT_TEMPLATE_FILE = '@PickwareWmsBundle/documents/shipping-process-receipt.html.twig';

    public function __construct(
        private readonly DocumentTemplateRenderer $documentTemplateRenderer,
        private readonly DomPdfRenderer $domPdfRenderer,
        private readonly Translator $translator,
    ) {}

    /**
     * @param array{
     *  shippingProcessNumber: string,
     *  shippingProcessState: string,
     *  warehouse: WarehouseEntity,
     *  productNameQuantities: array<array{
     *      productNumber: string,
     *      name: string,
     *      quantity: int
     *  }>,
     *  barcode: string,
     *  localeCode: string,
     *  userName: ?string
     * } $templateVariables
     */
    public function generate(array $templateVariables, string $languageId, Context $context): RenderedDocument
    {
        $html = $this->documentTemplateRenderer->render(
            self::DOCUMENT_TEMPLATE_FILE,
            $templateVariables,
            $languageId,
            $context,
        );

        $config = new DocumentConfiguration();
        $config->setFilenamePrefix($this->getFileName($templateVariables['localeCode'], $context));
        $config->setFilenameSuffix('.' . FileTypes::PDF);
        $renderedDocument = new RenderedDocument(
            name: $config->buildName(),
            fileExtension: FileTypes::PDF,
            config: $config->jsonSerialize(),
        );
        $renderedDocument->setContent($this->domPdfRenderer->render($renderedDocument, $html));

        return $renderedDocument;
    }

    public function getFileName(string $localeCode, Context $context): string
    {
        $this->translator->setTranslationLocale($localeCode, $context);

        return sprintf(
            $this->translator->translate('pickware-wms.shipping-process-receipt.file-name'),
            (new DateTimeImmutable())->format('Y-m-d H_i_s'),
        );
    }
}
