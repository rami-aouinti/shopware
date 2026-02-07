<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Carrier\Model;

class CarrierEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const ENTITY_LOADED_EVENT = 'pickware_shipping_carrier.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\PartialEntityLoadedEvent")
     */
    public const ENTITY_PARTIAL_LOADED_EVENT = 'pickware_shipping_carrier.partial_loaded';
}
