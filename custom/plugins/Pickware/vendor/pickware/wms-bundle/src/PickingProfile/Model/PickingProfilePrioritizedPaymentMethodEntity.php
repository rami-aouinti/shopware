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
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PickingProfilePrioritizedPaymentMethodEntity extends Entity
{
    use EntityIdTrait;

    protected string $paymentMethodId;
    protected ?PaymentMethodEntity $paymentMethod = null;
    protected string $pickingProfileId;
    protected ?PickingProfileEntity $pickingProfile = null;

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }

    public function setPaymentMethodId(string $paymentMethodId): void
    {
        if ($this->paymentMethod?->getId() !== $paymentMethodId) {
            $this->paymentMethod = null;
        }
        $this->paymentMethodId = $paymentMethodId;
    }

    public function getPaymentMethod(): PaymentMethodEntity
    {
        if (!$this->paymentMethod) {
            throw new AssociationNotLoadedException('paymentMethod', $this);
        }

        return $this->paymentMethod;
    }

    public function setPaymentMethod(PaymentMethodEntity $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
        $this->paymentMethodId = $paymentMethod->getId();
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
