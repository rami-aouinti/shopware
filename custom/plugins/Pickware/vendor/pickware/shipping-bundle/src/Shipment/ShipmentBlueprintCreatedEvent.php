<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Shipment;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class ShipmentBlueprintCreatedEvent extends NestedEvent
{
    public const EVENT_NAME = 'pickware_shipping.shipment.blueprint_created';

    protected ShipmentBlueprint $shipmentBlueprint;
    protected string $orderId;
    protected Context $context;

    public function __construct(ShipmentBlueprint $shipmentBlueprint, string $orderId, Context $context)
    {
        $this->shipmentBlueprint = $shipmentBlueprint;
        $this->orderId = $orderId;
        $this->context = $context;
    }

    public function getShipmentBlueprint(): ShipmentBlueprint
    {
        return $this->shipmentBlueprint;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
