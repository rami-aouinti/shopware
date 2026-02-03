<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\SalesChannelContext\Model;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class SalesChannelApiContextEntity extends Entity
{
    protected string $salesChannelContextToken;
    protected array $payload;

    public function getSalesChannelContextToken(): string
    {
        return $this->salesChannelContextToken;
    }

    public function setSalesChannelContextToken(string $salesChannelContextToken): void
    {
        $this->salesChannelContextToken = $salesChannelContextToken;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    /**
     * Gets a value nested within the payload under the given path.
     * If the value for this path does not exist, returns null.
     *
     * @return null|array|mixed
     */
    public function getValue(array $path)
    {
        return array_reduce($path, fn(array $array, $key) => $array[$key] ?? null, $this->payload);
    }
}
