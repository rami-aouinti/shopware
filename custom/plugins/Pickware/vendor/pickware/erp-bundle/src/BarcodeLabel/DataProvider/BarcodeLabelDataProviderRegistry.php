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

use Pickware\PickwareErpStarter\Registry\AbstractRegistry;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class BarcodeLabelDataProviderRegistry extends AbstractRegistry
{
    public const DI_CONTAINER_TAG = 'pickware_erp.barcode_label_data_provider';

    public function __construct(
        #[TaggedIterator('pickware_erp.barcode_label_data_provider')]
        iterable $aggregators,
    ) {
        parent::__construct(
            $aggregators,
            [AbstractBarcodeLabelDataProvider::class],
            self::DI_CONTAINER_TAG,
        );
    }

    /**
     * @param AbstractBarcodeLabelDataProvider $instance
     */
    protected function getKey($instance): string
    {
        return $instance->getBarcodeLabelType();
    }

    public function getDataProviderByBarcodeLabelType(string $key): AbstractBarcodeLabelDataProvider
    {
        return $this->getRegisteredInstanceByKey($key);
    }
}
