<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Messenger;

use Doctrine\DBAL\Exception\DeadlockException;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Throwable;

#[AsDecorator('messenger.bus.default.middleware.handle_message')]
class DeadlockRetryMiddleware implements MiddlewareInterface
{
    public const MAX_RETRIES = 3;

    public function __construct(
        private readonly HandleMessageMiddleware $decoratedMiddleware,
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        return $this->handleWithRetry($envelope, $stack, 0);
    }

    private function handleWithRetry(Envelope $envelope, StackInterface $stack, int $attempt): Envelope
    {
        try {
            return $this->decoratedMiddleware->handle($envelope, $stack);
        } catch (HandlerFailedException $exception) {
            if ($attempt >= self::MAX_RETRIES || !$this->containsDeadlockException($exception)) {
                throw $exception;
            }

            // Small delay before retry to allow the deadlock to resolve
            usleep(100_000 * ($attempt + 1));

            return $this->handleWithRetry($envelope, $stack, $attempt + 1);
        }
    }

    private function containsDeadlockException(HandlerFailedException $exception): bool
    {
        foreach ($exception->getWrappedExceptions() as $wrappedException) {
            if ($this->isOrContainsDeadlockException($wrappedException)) {
                return true;
            }
        }

        return false;
    }

    private function isOrContainsDeadlockException(Throwable $exception): bool
    {
        if ($exception instanceof DeadlockException) {
            return true;
        }

        if ($exception->getPrevious() !== null) {
            return $this->isOrContainsDeadlockException($exception->getPrevious());
        }

        return false;
    }
}
