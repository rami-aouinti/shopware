<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Shipment\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\DocumentBundle\Document\Model\DocumentCollection;
use Pickware\ShippingBundle\Carrier\Model\CarrierEntity;
use Pickware\ShippingBundle\Shipment\ShipmentBlueprint;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class ShipmentEntity extends Entity
{
    use EntityIdTrait;

    protected ShipmentBlueprint $shipmentBlueprint;

    /**
     * carrier specific meta information for this shipment
     */
    protected ?array $metaInformation;

    protected bool $cancelled;
    protected bool $isReturnShipment;
    protected bool $cashOnDeliveryEnabled;
    protected string $carrierTechnicalName;
    protected ?CarrierEntity $carrier = null;
    protected ?TrackingCodeCollection $trackingCodes = null;
    protected ?DocumentCollection $documents = null;
    protected ?OrderCollection $orders = null;
    protected ?string $salesChannelId;
    protected ?SalesChannelEntity $salesChannel = null;

    public function getShipmentBlueprint(): ShipmentBlueprint
    {
        return $this->shipmentBlueprint;
    }

    public function setShipmentBlueprint(ShipmentBlueprint $shipmentBlueprint): void
    {
        $this->shipmentBlueprint = $shipmentBlueprint;
    }

    public function getMetaInformation(): ?array
    {
        return $this->metaInformation;
    }

    public function setMetaInformation(?array $metaInformation): void
    {
        $this->metaInformation = $metaInformation;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function setCancelled(bool $cancelled): void
    {
        $this->cancelled = $cancelled;
    }

    public function getCarrierTechnicalName(): string
    {
        return $this->carrierTechnicalName;
    }

    public function setCarrierTechnicalName(string $carrierTechnicalName): void
    {
        if ($this->carrier !== null && $this->carrier->getTechnicalName() !== $carrierTechnicalName) {
            $this->carrier = null;
        }
        $this->carrierTechnicalName = $carrierTechnicalName;
    }

    public function getCarrier(): CarrierEntity
    {
        if ($this->carrier === null) {
            throw new AssociationNotLoadedException('carrier', $this);
        }

        return $this->carrier;
    }

    public function setCarrier(CarrierEntity $carrier): void
    {
        $this->carrier = $carrier;
        $this->carrierTechnicalName = $carrier->getTechnicalName();
    }

    public function getTrackingCodes(): TrackingCodeCollection
    {
        if ($this->trackingCodes === null) {
            throw new AssociationNotLoadedException('trackingCodes', $this);
        }

        return $this->trackingCodes;
    }

    public function setTrackingCodes(TrackingCodeCollection $trackingCodes): void
    {
        $this->trackingCodes = $trackingCodes;
    }

    public function getDocuments(): DocumentCollection
    {
        if ($this->documents === null) {
            throw new AssociationNotLoadedException('documents', $this);
        }

        return $this->documents;
    }

    public function setDocuments(DocumentCollection $documents): void
    {
        $this->documents = $documents;
    }

    public function getOrders(): OrderCollection
    {
        if ($this->orders === null) {
            throw new AssociationNotLoadedException('orders', $this);
        }

        return $this->orders;
    }

    public function setOrders(OrderCollection $orders): void
    {
        $this->orders = $orders;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        if ($this->salesChannel === null && $this->salesChannelId !== null) {
            throw new AssociationNotLoadedException('salesChannel', $this);
        }

        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
        if ($salesChannel !== null) {
            $this->salesChannelId = $salesChannel->getId();
        }
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
        if (
            $salesChannelId === null
            || ($this->salesChannel !== null && $this->salesChannel->getId() !== $salesChannelId)
        ) {
            $this->salesChannel = null;
        }
    }

    public function getIsReturnShipment(): bool
    {
        return $this->isReturnShipment;
    }

    public function setIsReturnShipment(bool $isReturnShipment): void
    {
        $this->isReturnShipment = $isReturnShipment;
    }

    public function getCashOnDeliveryEnabled(): bool
    {
        return $this->cashOnDeliveryEnabled;
    }

    public function setCashOnDeliveryEnabled(bool $cashOnDeliveryEnabled): void
    {
        $this->cashOnDeliveryEnabled = $cashOnDeliveryEnabled;
    }
}
