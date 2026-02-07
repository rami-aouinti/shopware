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

use DateTimeImmutable;
use JsonSerializable;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
readonly class PickwareLicense implements JsonSerializable
{
    /**
     * @param string $licenseUuid acts as a license-key, identifies the license
     */
    public function __construct(
        private string $licenseUuid,
        private string $shopUuid,
        private string $organizationUuid,
        private DateTimeImmutable $pickwareAccountConnectedAt,
    ) {}

    public static function fromArray(array $array): self
    {
        return new self(
            licenseUuid: $array['licenseUuid'],
            shopUuid: $array['shopUuid'],
            organizationUuid: $array['organizationUuid'],
            pickwareAccountConnectedAt: new DateTimeImmutable(
                $array['pickwareAccountConnectedAt'],
            ),
        );
    }

    public function getLicenseUuid(): string
    {
        return $this->licenseUuid;
    }

    public function getShopUuid(): string
    {
        return $this->shopUuid;
    }

    public function getOrganizationUuid(): string
    {
        return $this->organizationUuid;
    }

    public function getPickwareAccountConnectedAt(): DateTimeImmutable
    {
        return $this->pickwareAccountConnectedAt;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'licenseUuid' => $this->licenseUuid,
            'shopUuid' => $this->shopUuid,
            'organizationUuid' => $this->organizationUuid,
            'pickwareAccountConnectedAt' => $this->pickwareAccountConnectedAt->format(DATE_ATOM),
        ];
    }
}
