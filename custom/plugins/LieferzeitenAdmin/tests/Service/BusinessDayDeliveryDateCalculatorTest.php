<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use LieferzeitenAdmin\Service\BusinessDayDeliveryDateCalculator;
use PHPUnit\Framework\TestCase;

class BusinessDayDeliveryDateCalculatorTest extends TestCase
{
    public function testCalculateReturnsNullWithoutBaseDate(): void
    {
        $calculator = new BusinessDayDeliveryDateCalculator();

        static::assertNull($calculator->calculate(null, ['workingDays' => 2, 'cutoff' => '14:00']));
    }

    public function testCalculateKeepsSameBusinessDayWhenNoWorkingDaysAndBeforeCutoff(): void
    {
        $calculator = new BusinessDayDeliveryDateCalculator();
        $baseDate = new \DateTimeImmutable('2026-02-03 10:00:00', new \DateTimeZone('Europe/Berlin'));

        $result = $calculator->calculate($baseDate, ['workingDays' => 0, 'cutoff' => '14:00']);

        static::assertSame('2026-02-03', $result?->format('Y-m-d'));
    }

    public function testCalculateShiftsToNextBusinessDayWhenAfterCutoff(): void
    {
        $calculator = new BusinessDayDeliveryDateCalculator();
        $baseDate = new \DateTimeImmutable('2026-02-03 16:15:00', new \DateTimeZone('Europe/Berlin'));

        $result = $calculator->calculate($baseDate, ['workingDays' => 0, 'cutoff' => '14:00']);

        static::assertSame('2026-02-04', $result?->format('Y-m-d'));
    }

    public function testCalculateSkipsWeekendForBusinessDayDeadlines(): void
    {
        $calculator = new BusinessDayDeliveryDateCalculator();
        $baseDate = new \DateTimeImmutable('2026-02-06 16:30:00', new \DateTimeZone('Europe/Berlin')); // Friday after cutoff

        $result = $calculator->calculate($baseDate, ['workingDays' => 1, 'cutoff' => '14:00']);

        static::assertSame('2026-02-10', $result?->format('Y-m-d')); // Tuesday
        static::assertSame('2', $result?->format('N'));
    }

    public function testCalculateNormalizesNegativeWorkingDaysToZero(): void
    {
        $calculator = new BusinessDayDeliveryDateCalculator();
        $baseDate = new \DateTimeImmutable('2026-02-07 09:00:00', new \DateTimeZone('Europe/Berlin')); // Saturday

        $result = $calculator->calculate($baseDate, ['workingDays' => -3, 'cutoff' => '14:00']);

        static::assertSame('2026-02-09', $result?->format('Y-m-d')); // Monday
    }
}
