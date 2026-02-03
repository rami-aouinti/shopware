<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\ApiVersion;

use Pickware\ApiVersioningBundle\ApiVersion;

final class ApiVersion20240703 extends ApiVersion
{
    public function __construct()
    {
        parent::__construct('2024-07-03');
    }
}
