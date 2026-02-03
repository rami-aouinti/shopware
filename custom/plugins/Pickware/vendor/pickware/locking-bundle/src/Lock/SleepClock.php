<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LockingBundle\Lock;

use InvalidArgumentException;

class SleepClock
{
    public function sleep(float $sleepTimeInSeconds): void
    {
        if ($sleepTimeInSeconds < 0) {
            throw new InvalidArgumentException('The sleep time must be greater than or equal to 0.');
        }

        usleep((int) ($sleepTimeInSeconds * 1_000_000));
    }
}
