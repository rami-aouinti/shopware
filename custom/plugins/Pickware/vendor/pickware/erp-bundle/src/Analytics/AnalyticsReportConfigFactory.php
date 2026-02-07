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

#[AutoconfigureTag('pickware_erp.analytics_report_config_factory')]
interface AnalyticsReportConfigFactory
{
    public function createCalculatorConfigFromArray(array $serialized): JsonSerializable;

    public function createDefaultCalculatorConfig(): JsonSerializable;

    public function getReportTechnicalName(): string;
}
