<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Delivery\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class DeliveryParcelTrackingCodeEntity extends Entity
{
    use EntityIdTrait;

    protected string $deliveryParcelId;
    protected ?DeliveryParcelEntity $deliveryParcel = null;
    protected string $code;
    protected ?string $trackingUrl;

    public function getParcelId(): string
    {
        return $this->deliveryParcelId;
    }

    public function setDeliveryParcelId(string $deliveryParcelId): void
    {
        if ($this->deliveryParcel && $this->deliveryParcel->getId() !== $deliveryParcelId) {
            $this->deliveryParcel = null;
        }

        $this->deliveryParcelId = $deliveryParcelId;
    }

    public function getDeliveryParcel(): DeliveryParcelEntity
    {
        if (!$this->deliveryParcel) {
            throw new AssociationNotLoadedException('deliveryParcel', $this);
        }

        return $this->deliveryParcel;
    }

    public function setDeliveryParcel(DeliveryParcelEntity $parcel): void
    {
        $this->deliveryParcelId = $parcel->getId();
        $this->deliveryParcel = $parcel;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getTrackingUrl(): ?string
    {
        return $this->trackingUrl;
    }

    public function setTrackingUrl(?string $trackingUrl): void
    {
        $this->trackingUrl = $trackingUrl;
    }
}
