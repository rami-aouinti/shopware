<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Statistic\Dto;

use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
readonly class TimePeriod
{
    public function __construct(
        public DateTimeImmutable $fromDateTime,
        public DateTimeImmutable $toDateTime,
    ) {}

    /**
     * @param array{fromDateTime: string, toDateTime: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            new DateTimeImmutable($data['fromDateTime']),
            new DateTimeImmutable($data['toDateTime']),
        );
    }
}
