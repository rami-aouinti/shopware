<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UsageReportBundle\ApiClient\Model;

use JsonSerializable;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
readonly class PickwareShop implements JsonSerializable
{
    public function __construct(
        private string $shopUuid,
        private string $organizationUuid,
    ) {}

    /**
     * @param array{'shopUuid': string, 'organizationUuid': string} $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            shopUuid: $array['shopUuid'],
            organizationUuid: $array['organizationUuid'],
        );
    }

    public function getShopUuid(): string
    {
        return $this->shopUuid;
    }

    public function getOrganizationUuid(): string
    {
        return $this->organizationUuid;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'shopUuid' => $this->shopUuid,
            'organizationUuid' => $this->organizationUuid,
        ];
    }
}
