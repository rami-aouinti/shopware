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

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;

class LockHandlerFactory
{
    public const DEFAULT_SLEEP_TIME_PER_LOCK_ACQUIRE_IN_SECONDS = 0.5;
    public const DEFAULT_LOCK_MAX_TIME_TO_LIVE_IN_SECONDS = 120;
    public const DEFAULT_LOCK_MAX_LOCK_ACQUIRE_WAIT_IN_SECONDS = 15;
    public const DEFAULT_LOCK_MAX_RETRIES = 100;

    public function __construct(
        #[Autowire(service: 'pickware_locking_bundle.lock_factory')]
        private readonly LockFactory $lockFactory,
        private readonly SleepClock $sleepClock,
    ) {}

    // Use this method in the autowiring expression when injecting a LockHandler using the default configuration
    public function createLockHandler(
        float $sleepTimePerLockAcquireInSeconds = self::DEFAULT_SLEEP_TIME_PER_LOCK_ACQUIRE_IN_SECONDS,
        float $lockMaxTimeToLiveInSeconds = self::DEFAULT_LOCK_MAX_TIME_TO_LIVE_IN_SECONDS,
        float $lockMaxLockAcquireWaitInSeconds = self::DEFAULT_LOCK_MAX_LOCK_ACQUIRE_WAIT_IN_SECONDS,
        int $lockMaxRetries = self::DEFAULT_LOCK_MAX_RETRIES,
    ): LockHandler {
        return new LockHandler(
            sleepTimePerLockAcquireInSeconds: $sleepTimePerLockAcquireInSeconds,
            lockMaxTimeToLiveInSeconds: $lockMaxTimeToLiveInSeconds,
            lockMaxLockAcquireWaitInSeconds: $lockMaxLockAcquireWaitInSeconds,
            lockMaxRetries: $lockMaxRetries,
            lockFactory: $this->lockFactory,
            sleepClock: $this->sleepClock,
        );
    }
}
