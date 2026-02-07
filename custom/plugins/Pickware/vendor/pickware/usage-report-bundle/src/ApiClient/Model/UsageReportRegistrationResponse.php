<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UsageReportBundle\ApiClient\Model;

use DateTimeImmutable;
use DateTimeZone;
use function Pickware\PhpStandardLibrary\Optional\doIf;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
readonly class UsageReportRegistrationResponse
{
    public function __construct(
        private string $usageReportUuid,
        private DateTimeImmutable $reportedAt,
        private ?DateTimeImmutable $inclusiveIntervalStart,
    ) {}

    /**
     * @param array{'usageReportUuid': string, 'reportedAt': string, 'inclusiveIntervalStart': ?string} $data
     */
    public static function fromArray(array $data): self
    {
        /** @var string|null $inclusiveIntervalStart */
        $inclusiveIntervalStart = $data['inclusiveIntervalStart'] ?? null;

        return new self(
            usageReportUuid: $data['usageReportUuid'],
            reportedAt: new DateTimeImmutable($data['reportedAt']),
            inclusiveIntervalStart: doIf(
                $inclusiveIntervalStart,
                fn(string $inclusiveIntervalStart) => (new DateTimeImmutable($inclusiveIntervalStart))->setTimezone(new DateTimeZone('UTC')),
            ),
        );
    }

    public function getUsageReportUuid(): string
    {
        return $this->usageReportUuid;
    }

    public function getReportedAt(): DateTimeImmutable
    {
        return $this->reportedAt;
    }

    public function getUsageReportId(): string
    {
        return str_replace('-', '', $this->getUsageReportUuid());
    }

    public function getInclusiveIntervalStart(): ?DateTimeImmutable
    {
        return $this->inclusiveIntervalStart;
    }
}
