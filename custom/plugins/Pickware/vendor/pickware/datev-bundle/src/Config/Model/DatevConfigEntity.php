<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Config\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\DatevBundle\Config\ConfigService;
use Pickware\DatevBundle\Config\Values\ConfigValues;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class DatevConfigEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $salesChannelId = null;
    protected ?SalesChannelEntity $salesChannel = null;
    protected ConfigValues $values;

    public function isDefaultConfig(): bool
    {
        return $this->getId() === ConfigService::DEFAULT_CONFIG_ID;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        if ($this->salesChannel && $this->salesChannel->getId() !== $salesChannelId) {
            $this->salesChannel = null;
        }
        $this->salesChannelId = $salesChannelId;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        if (!$this->salesChannel && $this->salesChannelId !== null) {
            throw new AssociationNotLoadedException('salesChannel', $this);
        }

        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        if ($salesChannel) {
            $this->salesChannelId = $salesChannel->getId();
        }
        $this->salesChannel = $salesChannel;
    }

    public function getValues(): ConfigValues
    {
        return $this->values;
    }

    public function setValues(ConfigValues $values): void
    {
        $this->values = $values;
    }
}
