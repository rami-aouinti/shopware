<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareAccountBundle\ApiClient\Model;

use DateTimeInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
readonly class PickwareLicenseLeaseOptions
{
    public function __construct(
        private string $licenseUuid,
        private string $installationUuid,
        private string $shopUuid,
        private string $shopUrl,
        private DateTimeInterface $serverTime,
    ) {}

    public function getLicenseUuid(): string
    {
        return $this->licenseUuid;
    }

    public function getInstallationUuid(): string
    {
        return $this->installationUuid;
    }

    public function getShopUuid(): string
    {
        return $this->shopUuid;
    }

    public function getShopUrl(): string
    {
        return $this->shopUrl;
    }

    public function getServerTime(): DateTimeInterface
    {
        return $this->serverTime;
    }
}
