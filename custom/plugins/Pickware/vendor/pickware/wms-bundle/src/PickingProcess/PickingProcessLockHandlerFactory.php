<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProcess;

use Pickware\LockingBundle\Lock\LockHandler;
use Pickware\LockingBundle\Lock\LockHandlerFactory;

class PickingProcessLockHandlerFactory extends LockHandlerFactory
{
    /**
     * A longer lock acquire wait time is needed for the picking process creation lock because the picking process
     * creation might take a while when starting e.g. a batch picking process with many orders.
     */
    public const PICKING_PROCESS_CREATION_LOCK_MAX_LOCK_ACQUIRE_WAIT_IN_SECONDS = 60;

    public function createPickingProcessCreationLockHandler(): LockHandler
    {
        return $this->createLockHandler(
            lockMaxLockAcquireWaitInSeconds: self::PICKING_PROCESS_CREATION_LOCK_MAX_LOCK_ACQUIRE_WAIT_IN_SECONDS,
        );
    }
}
