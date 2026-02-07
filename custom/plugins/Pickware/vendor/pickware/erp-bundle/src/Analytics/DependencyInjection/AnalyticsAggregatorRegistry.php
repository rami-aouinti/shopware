<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Analytics\DependencyInjection;

use Pickware\PickwareErpStarter\Analytics\AnalyticsAggregator;
use Pickware\PickwareErpStarter\Registry\AbstractRegistry;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class AnalyticsAggregatorRegistry extends AbstractRegistry
{
    public function __construct(
        #[TaggedIterator('pickware_erp.analytics_aggregator')]
        iterable $aggregators,
    ) {
        parent::__construct(
            $aggregators,
            [AnalyticsAggregator::class],
            'pickware_erp.analytics_aggregator',
        );
    }

    /**
     * @param AnalyticsAggregator $instance
     */
    protected function getKey($instance): string
    {
        return $instance->getAggregationTechnicalName();
    }

    public function getAnalyticsAggregatorByAggregationTechnicalName(string $aggregationTechnicalName): AnalyticsAggregator
    {
        return $this->getRegisteredInstanceByKey($aggregationTechnicalName);
    }
}
