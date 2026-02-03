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
use Pickware\ShippingBundle\Shipment\Model\TrackingCodeCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class DeliveryParcelEntity extends Entity
{
    use EntityIdTrait;

    protected string $deliveryId;
    protected ?DeliveryEntity $delivery = null;
    protected ?TrackingCodeCollection $trackingCodes = null;
    protected bool $shipped;

    public function getDeliveryId(): string
    {
        return $this->deliveryId;
    }

    public function setDeliveryId(string $deliveryId): void
    {
        if ($this->delivery?->getId() !== $deliveryId) {
            $this->delivery = null;
        }

        $this->deliveryId = $deliveryId;
    }

    public function getDelivery(): DeliveryEntity
    {
        if (!$this->delivery) {
            throw new AssociationNotLoadedException('delivery', $this);
        }

        return $this->delivery;
    }

    public function setDelivery(DeliveryEntity $delivery): void
    {
        $this->deliveryId = $delivery->getId();
        $this->delivery = $delivery;
    }

    public function getTrackingCodes(): TrackingCodeCollection
    {
        if (!$this->trackingCodes) {
            throw new AssociationNotLoadedException('trackingCodes', $this);
        }

        return $this->trackingCodes;
    }

    public function setTrackingCodes(?TrackingCodeCollection $trackingCodes): void
    {
        $this->trackingCodes = $trackingCodes;
    }

    public function getShipped(): bool
    {
        return $this->shipped;
    }

    public function setShipped(bool $shipped): void
    {
        $this->shipped = $shipped;
    }
}
