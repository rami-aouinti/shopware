<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt\Model;

enum GoodsReceiptLineItemDestinationAssignmentSource: string
{
    case Unset = 'unset';
    case Automatic = 'automatic';
    case Manual = 'manual';
}
