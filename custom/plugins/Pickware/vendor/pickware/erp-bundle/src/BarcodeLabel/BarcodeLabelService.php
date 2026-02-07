<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\BarcodeLabel;

use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PickwareErpStarter\BarcodeLabel\DataProvider\BarcodeLabelDataProviderRegistry;
use Pickware\PickwareErpStarter\BarcodeLabel\DataProvider\ProductDataProvider;
use RuntimeException;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Framework\Context;

class BarcodeLabelService
{
    public function __construct(
        private readonly BarcodeLabelDataProviderRegistry $barcodeLabelDataProviderRegistry,
        private readonly BarcodeLabelRenderer $barcodeLabelRenderer,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    public function createBarcodeLabels(
        BarcodeLabelConfiguration $labelConfiguration,
        Context $context,
    ): RenderedDocument {
        if (
            !$this->featureFlagService->isActive('pickware-erp.feature.product-barcode-label-creation')
            && $labelConfiguration->getBarcodeLabelType() === ProductDataProvider::BARCODE_LABEL_TYPE
        ) {
            throw new RuntimeException('It is not allowed to create product barcode labels when the feature is disabled.');
        }

        $dataProvider = $this->barcodeLabelDataProviderRegistry->getDataProviderByBarcodeLabelType(
            $labelConfiguration->getBarcodeLabelType(),
        );

        return $this->barcodeLabelRenderer->render(
            $labelConfiguration,
            $dataProvider->getData($labelConfiguration, $context),
        );
    }
}
