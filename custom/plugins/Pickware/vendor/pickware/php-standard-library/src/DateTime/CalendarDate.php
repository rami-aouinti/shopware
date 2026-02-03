<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PhpStandardLibrary\DateTime;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use JsonSerializable;

readonly class CalendarDate implements JsonSerializable
{
    /**
     * @param int<0,9999> $year
     * @param int<1,12> $month
     * @param int<1,31> $day
     */
    private function __construct(
        private int $year,
        private int $month,
        private int $day,
    ) {}

    /**
     * DateTime represents a moment in time, but the actual calendar date depends on where you are in the world. This
     * is why you need to provide a timezone to convert the DateTime to a CalendarDate.
     */
    public static function fromDateTimeInTimezone(
        DateTimeInterface $time,
        DateTimeZone $timezone,
    ): self {
        $dateTime = DateTimeImmutable::createFromInterface($time)->setTimezone($timezone);

        return new self(
            (int) $dateTime->format('Y'),
            (int) $dateTime->format('m'),
            (int) $dateTime->format('d'),
        );
    }

    public static function fromIsoString(string $isoString): self
    {
        $dateTime = DateTimeImmutable::createFromFormat('Y-m-d', $isoString);
        if ($dateTime === false) {
            throw new InvalidArgumentException('Invalid ISO date string provided');
        }
        if ($dateTime->format('Y-m-d') !== $isoString) {
            throw new InvalidArgumentException('Invalid ISO date string provided');
        }

        return new self(
            (int) $dateTime->format('Y'),
            (int) $dateTime->format('m'),
            (int) $dateTime->format('d'),
        );
    }

    public function toIsoString(): string
    {
        return sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    public function getDay(): int
    {
        return $this->day;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toIsoString();
    }

    public function getAsDateTimeRepresentingBeginOfDayInTimeZone(
        DateTimeZone $timezone,
    ): DateTimeImmutable {
        return DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            sprintf('%04d-%02d-%02d 00:00:00', $this->year, $this->month, $this->day),
            $timezone,
        );
    }
}
