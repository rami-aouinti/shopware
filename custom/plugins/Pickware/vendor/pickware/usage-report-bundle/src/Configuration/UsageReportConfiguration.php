<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UsageReportBundle\Configuration;

use Pickware\UsageReportBundle\ApiClient\Model\PickwareShop;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
readonly class UsageReportConfiguration
{
    public function __construct(
        private PickwareShop $pickwareShop,
        private ?string $licenseUuid,
    ) {}

    public function getPickwareShop(): PickwareShop
    {
        return $this->pickwareShop;
    }

    public function getLicenseUuid(): ?string
    {
        return $this->licenseUuid;
    }
}
