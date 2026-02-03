<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Authentication;

use League\Flysystem\FilesystemOperator;
use Psr\Clock\ClockInterface;

class PrivateFileSystemCachedTokenRetriever implements CachedTokenRetriever
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly FilesystemOperator $fileSystem,
        private readonly TokenRetriever $tokenRetriever,
    ) {}

    public function retrieveToken(Credentials $credentials): Token
    {
        $session = $this->getSessionForCredentials($credentials);

        if (!$session || !$session->getToken()->isValidAtTime($this->clock->now())) {
            $session = $this->generateNewSession($credentials);
        }

        return $session->getToken();
    }

    private function generateNewSession(Credentials $credentials): Session
    {
        $token = $this->tokenRetriever->retrieveToken($credentials);
        $session = Session::create($token, $credentials);
        $this->fileSystem->write($this->getStoragePath($credentials), $session->toString());

        return $session;
    }

    private function getSessionForCredentials(Credentials $credentials): ?Session
    {
        $storagePath = $this->getStoragePath($credentials);

        if (!$this->fileSystem->has($storagePath)) {
            return null;
        }

        $session = Session::fromString($this->fileSystem->read($storagePath));
        if (!$session->matches($credentials)) {
            return null;
        }

        return $session;
    }

    private function getStoragePath(Credentials $credentials): string
    {
        return sprintf('%s', md5($credentials->getUsername()));
    }

    public function invalidateCache(Credentials $credentials): void
    {
        $this->fileSystem->delete($this->getStoragePath($credentials));
    }
}
