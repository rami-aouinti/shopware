<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ApiVersioningBundle;

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

class ApiVersion
{
    private const PICKWARE_API_VERSION_REQUEST_HEADER = 'X-Pickware-Api-Version';

    private readonly int $year;
    private readonly int $month;
    private readonly int $day;

    public function __construct(string $rawVersion)
    {
        $versionParts = explode('-', $rawVersion);
        if (count($versionParts) !== 3) {
            throw new InvalidArgumentException(sprintf(
                'Value "%1$s" passed to %2$s is not a valid version string. Expected date in format "YYYY-MM-DD".',
                $rawVersion,
                self::class,
            ));
        }

        $this->year = (int) $versionParts[0];
        $this->month = (int) $versionParts[1];
        $this->day = (int) $versionParts[2];
    }

    public static function getVersionFromRequest(Request $request): ?ApiVersion
    {
        $rawRequestVersion = $request->headers->get(self::PICKWARE_API_VERSION_REQUEST_HEADER);
        if ($rawRequestVersion === null) {
            return null;
        }

        return new ApiVersion($rawRequestVersion);
    }

    public function compareTo(ApiVersion $otherVersion): int
    {
        if ($this->year !== $otherVersion->year) {
            return $this->year <=> $otherVersion->year;
        }

        if ($this->month !== $otherVersion->month) {
            return $this->month <=> $otherVersion->month;
        }

        return $this->day <=> $otherVersion->day;
    }

    public function isNewerThan(ApiVersion $otherVersion): bool
    {
        return $this->compareTo($otherVersion) > 0;
    }
}
