<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProfile\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PickingProfilePrioritizedShippingMethodEntity extends Entity
{
    use EntityIdTrait;

    protected string $shippingMethodId;
    protected ?ShippingMethodEntity $shippingMethod = null;
    protected string $pickingProfileId;
    protected ?PickingProfileEntity $pickingProfile = null;

    public function getShippingMethodId(): string
    {
        return $this->shippingMethodId;
    }

    public function setShippingMethodId(string $shippingMethodId): void
    {
        if ($this->shippingMethod?->getId() !== $shippingMethodId) {
            $this->shippingMethod = null;
        }
        $this->shippingMethodId = $shippingMethodId;
    }

    public function getShippingMethod(): ShippingMethodEntity
    {
        if (!$this->shippingMethod) {
            throw new AssociationNotLoadedException('shippingMethod', $this);
        }

        return $this->shippingMethod;
    }

    public function setShippingMethod(ShippingMethodEntity $shippingMethod): void
    {
        $this->shippingMethod = $shippingMethod;
        $this->shippingMethodId = $shippingMethod->getId();
    }

    public function getPickingProfileId(): string
    {
        return $this->pickingProfileId;
    }

    public function setPickingProfileId(string $pickingProfileId): void
    {
        if ($this->pickingProfile?->getId() !== $pickingProfileId) {
            $this->pickingProfile = null;
        }
        $this->pickingProfileId = $pickingProfileId;
    }

    public function getPickingProfile(): PickingProfileEntity
    {
        if (!$this->pickingProfile) {
            throw new AssociationNotLoadedException('pickingProfile', $this);
        }

        return $this->pickingProfile;
    }

    public function setPickingProfile(PickingProfileEntity $pickingProfile): void
    {
        $this->pickingProfile = $pickingProfile;
        $this->pickingProfileId = $pickingProfile->getId();
    }
}
