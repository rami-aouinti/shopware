<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ReturnOrder\Document;

use DateTimeImmutable;
use InvalidArgumentException;
use Pickware\DocumentBundle\Renderer\DocumentTemplateRenderer;
use Pickware\DocumentBundle\Renderer\DomPdfRenderer;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PickwareErpStarter\GoodsReceipt\FeatureFlags\GoodsReceiptForReturnOrderDevFeatureFlag;
use Pickware\PickwareErpStarter\Translation\Translator;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Framework\Context;

class ReturnOrderStockingListDocumentGenerator
{
    public const DOCUMENT_TEMPLATE_FILE = '@PickwareErpBundle/documents/return-order-stocking-list.html.twig';

    public function __construct(
        private readonly DocumentTemplateRenderer $documentTemplateRenderer,
        private readonly DomPdfRenderer $pdfRenderer,
        private readonly Translator $translator,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    public function generate(array $templateVariables, string $languageId, Context $context): RenderedDocument
    {
        if ($this->featureFlagService->isActive(GoodsReceiptForReturnOrderDevFeatureFlag::NAME)) {
            // Note: When the feature flag is removed, the whole code for generating a return order stocking list has
            // to be removed as well.
            throw new InvalidArgumentException(sprintf(
                'Creating a return order stocking list is not allowed when the feature flag "%s" is enabled.',
                GoodsReceiptForReturnOrderDevFeatureFlag::NAME,
            ));
        }

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
        $renderedDocument->setContent($this->pdfRenderer->render($renderedDocument, $html));

        return $renderedDocument;
    }

    public function getFileName(string $localeCode, Context $context): string
    {
        $this->translator->setTranslationLocale($localeCode, $context);

        return sprintf(
            $this->translator->translate('pickware-erp-starter.return-order-stocking-list.file-name'),
            (new DateTimeImmutable())->format('Y-m-d H_i_s'),
        );
    }
}
