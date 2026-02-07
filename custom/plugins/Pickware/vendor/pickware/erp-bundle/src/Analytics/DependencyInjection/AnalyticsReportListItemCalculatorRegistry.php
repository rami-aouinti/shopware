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

use Pickware\PickwareErpStarter\Analytics\AnalyticsReportListItemCalculator;
use Pickware\PickwareErpStarter\Registry\AbstractRegistry;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class AnalyticsReportListItemCalculatorRegistry extends AbstractRegistry
{
    public function __construct(
        #[TaggedIterator('pickware_erp.analytics_report_list_item_calculator')]
        iterable $reportListItemCalculators,
    ) {
        parent::__construct(
            $reportListItemCalculators,
            [AnalyticsReportListItemCalculator::class],
            'pickware_erp.analytics_report_list_item_calculator',
        );
    }

    /**
     * @param AnalyticsReportListItemCalculator $instance
     */
    protected function getKey($instance): string
    {
        return $instance->getReportTechnicalName();
    }

    public function getAnalyticsReportListItemCalculatorByReportTechnicalName(string $reportTechnicalName): AnalyticsReportListItemCalculator
    {
        return $this->getRegisteredInstanceByKey($reportTechnicalName);
    }
}
