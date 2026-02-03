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

enum ShipmentType: string
{
    case CommercialSample = 'commercial-sample';
    case Documents = 'documents';
    case Gift = 'gift';
    case Other = 'other';
    case ReturnedGoods = 'returned-goods';
    case SaleOfGoods = 'sale-of-goods';
}
