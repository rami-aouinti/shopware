<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PaperTrail;

use JsonSerializable;
use Shopware\Core\Framework\Uuid\Uuid;

abstract class AbstractPaperTrailUri implements JsonSerializable
{
    private const URI_SCHEME = 'pickware-paper-trail';

    protected string $uuid;

    public function __construct()
    {
        $this->uuid = Uuid::randomHex();
    }

    public static function fromString(string $uri): self
    {
        return self::fromParts(...self::parseUri($uri));
    }

    public static function getFallbackUri(): self
    {
        return self::fromParts(...self::getFallbackValues());
    }

    private static function fromParts(string $responsibleBundle, string $processName, string $uuid): self
    {
        return new class ($responsibleBundle, $processName, $uuid) extends AbstractPaperTrailUri {
            public function __construct(
                private readonly string $responsibleBundle,
                private readonly string $processName,
                string $uuid,
            ) {
                parent::__construct();
                $this->uuid = $uuid;
            }

            protected function getResponsibleBundle(): string
            {
                return $this->responsibleBundle;
            }

            protected function getProcessName(): string
            {
                return $this->processName;
            }
        };
    }

    /**
     * @return array{responsibleBundle: string, processName: string, uuid: string}
     */
    private static function parseUri(string $uri): array
    {
        $parts = parse_url($uri);
        if (
            !isset($parts['scheme'], $parts['host'], $parts['path'], $parts['fragment'])
            || $parts['scheme'] !== self::URI_SCHEME
        ) {
            return self::getFallbackValues();
        }

        return [
            'responsibleBundle' => $parts['host'],
            'processName' => mb_substr($parts['path'], 1), // parse_url does not strip the leading slash
            'uuid' => $parts['fragment'],
        ];
    }

    /**
     * @return array{responsibleBundle: string, processName: string, uuid: string}
     */
    private static function getFallbackValues(): array
    {
        return [
            'responsibleBundle' => 'unknown',
            'processName' => 'unknown',
            'uuid' => Uuid::randomHex(),
        ];
    }

    abstract protected function getResponsibleBundle(): string;

    abstract protected function getProcessName(): string;

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getUri(): string
    {
        return sprintf(
            '%s://%s/%s#%s',
            self::URI_SCHEME,
            $this->getResponsibleBundle(),
            $this->getProcessName(),
            $this->uuid,
        );
    }

    public function jsonSerialize(): mixed
    {
        return $this->getUri();
    }
}
