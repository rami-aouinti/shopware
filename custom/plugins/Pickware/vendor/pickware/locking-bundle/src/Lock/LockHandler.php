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

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Lock\Exception\LockReleasingException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

#[Exclude]
class LockHandler implements ResetInterface
{
    private float $totalSleepTime = 0.0;

    public function __construct(
        private readonly float $sleepTimePerLockAcquireInSeconds,
        private readonly float $lockMaxTimeToLiveInSeconds,
        private readonly float $lockMaxLockAcquireWaitInSeconds,
        private readonly int $lockMaxRetries,
        private readonly LockFactory $lockFactory,
        private readonly SleepClock $sleepClock,
        private readonly ?ExceptionLogger $exceptionLogger = null,
    ) {}

    public function getTotalSleepTime(): float
    {
        return $this->totalSleepTime;
    }

    /**
     * Important Information: Nested calls with the same ID result in a deadlock! This is because under the hood each
     * call creates a new lock key, whose state is not shared with the outer locks.
     */
    public function lockPessimistically(LockIdProvider $lockIdProvider, callable $callback): mixed
    {
        $lock = $this->lockFactory->createLock($lockIdProvider->getLockId(), ttl: $this->lockMaxTimeToLiveInSeconds);

        $currentLockSleepTime = 0.0;
        $currentLockRetries = 0;
        while (!$lock->acquire()) {
            if ($currentLockRetries >= $this->lockMaxRetries) {
                throw LockException::maxRetriesReached($this->lockMaxRetries, $lockIdProvider);
            }
            if ($currentLockSleepTime >= $this->lockMaxLockAcquireWaitInSeconds) {
                throw LockException::maxWaitTimeReached($this->lockMaxLockAcquireWaitInSeconds, $lockIdProvider);
            }

            $this->sleepClock->sleep($this->sleepTimePerLockAcquireInSeconds);

            $currentLockRetries++;
            $currentLockSleepTime += $this->sleepTimePerLockAcquireInSeconds;
            $this->totalSleepTime += $this->sleepTimePerLockAcquireInSeconds;
        }

        try {
            $value = $callback();
            $lock->release();

            return $value;
        } catch (Throwable $exception) {
            if ($exception instanceof LockReleasingException) {
                $this->exceptionLogger?->logException(
                    throwable: $exception,
                    meta: ['lockId' => $lockIdProvider->getLockId()],
                );

                throw $exception;
            }

            try {
                $lock->release();
            } catch (LockReleasingException $lockReleasingException) {
                $this->exceptionLogger?->logException(
                    throwable: $lockReleasingException,
                    meta: ['lockId' => $lockIdProvider->getLockId()],
                );

                throw new LockReleasingException(
                    'Failed to release lock after callback threw an exception',
                    previous: $exception,
                );
            }

            throw $exception;
        }
    }

    public function reset(): void
    {
        $this->totalSleepTime = 0.0;
    }
}
