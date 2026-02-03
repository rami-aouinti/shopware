<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Carrier\Capabilities;

use Pickware\ShippingBundle\Config\Config;
use Pickware\ShippingBundle\Shipment\ShipmentsOperationResultSet;
use Shopware\Core\Framework\Context;

interface ReturnShipmentCancellationCapability extends ReturnShipmentsRegistrationCapability
{
    /**
     * @param string[] $shipmentIds
     */
    public function cancelReturnShipments(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet;
}
