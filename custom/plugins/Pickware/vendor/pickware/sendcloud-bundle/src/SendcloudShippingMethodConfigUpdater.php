<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SendcloudBundle;

use Doctrine\DBAL\Connection;
use Pickware\SendcloudBundle\Installation\SendcloudCarrier;
use Pickware\ShippingBundle\Config\ShippingMethodConfigUpdater;

class SendcloudShippingMethodConfigUpdater extends ShippingMethodConfigUpdater
{
    public function __construct(Connection $connection)
    {
        parent::__construct($connection, SendcloudCarrier::TECHNICAL_NAME);
    }
}
