<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Analytics;

use JsonSerializable;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('pickware_erp.analytics_aggregator_config_factory')]
interface AnalyticsAggregatorConfigFactory
{
    public function createAggregatorConfigFromArray(array $serialized): JsonSerializable;

    public function createDefaultAggregatorConfig(): JsonSerializable;

    public function getAggregationTechnicalName(): string;
}
