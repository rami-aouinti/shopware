<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\BarcodeLabel\DataProvider;

use InvalidArgumentException;
use Pickware\PickwareErpStarter\BarcodeLabel\BarcodeLabelConfiguration;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('pickware_erp.barcode_label_data_provider')]
abstract class AbstractBarcodeLabelDataProvider
{
    abstract public function getBarcodeLabelType(): string;

    abstract public function getSupportedLayouts(): array;

    abstract protected function collectLabelData(
        BarcodeLabelConfiguration $labelConfiguration,
        Context $context,
    ): array;

    public function getData(BarcodeLabelConfiguration $labelConfiguration, Context $context): array
    {
        $this->throwOnNonSupportedLayout($labelConfiguration);

        return $this->collectLabelData($labelConfiguration, $context);
    }

    private function throwOnNonSupportedLayout(BarcodeLabelConfiguration $labelConfiguration): void
    {
        if (!in_array($labelConfiguration->getLayout(), $this->getSupportedLayouts())) {
            throw new InvalidArgumentException(
                sprintf(
                    'Layout "%s" not supported by data provider "%s".',
                    $labelConfiguration->getLayout(),
                    self::class,
                ),
            );
        }
    }
}
