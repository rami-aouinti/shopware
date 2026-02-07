<?php declare(strict_types=1);

namespace LieferzeitenManagement\Service\Deadline;

final class DeadlineCalculator
{
    public function calculateLatestDate(\DateTimeInterface $baseDate, int $offsetDays, ?string $cutoffTime = null): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromInterface($baseDate);

        if ($cutoffTime) {
            $cutoff = \DateTimeImmutable::createFromInterface($baseDate)
                ->setTime(...$this->parseCutoffTime($cutoffTime));

            if ($date > $cutoff) {
                $date = $date->modify('+1 day');
            }
        }

        $daysToAdd = $offsetDays;
        while ($daysToAdd > 0) {
            $date = $date->modify('+1 day');

            if ($this->isWeekend($date)) {
                continue;
            }

            $daysToAdd--;
        }

        return $this->skipToWeekday($date);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function parseCutoffTime(string $cutoffTime): array
    {
        $parts = explode(':', $cutoffTime);
        $hour = isset($parts[0]) ? (int) $parts[0] : 12;
        $minute = isset($parts[1]) ? (int) $parts[1] : 0;

        return [$hour, $minute];
    }

    private function isWeekend(\DateTimeImmutable $date): bool
    {
        $dayOfWeek = (int) $date->format('N');

        return $dayOfWeek >= 6;
    }

    private function skipToWeekday(\DateTimeImmutable $date): \DateTimeImmutable
    {
        while ($this->isWeekend($date)) {
            $date = $date->modify('+1 day');
        }

        return $date;
    }
}
