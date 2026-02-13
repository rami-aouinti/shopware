<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

class BusinessDayDeliveryDateCalculator
{
    /**
     * @param array{workingDays:int,cutoff:string} $settings
     */
    public function calculate(?\DateTimeImmutable $baseDate, array $settings): ?\DateTimeImmutable
    {
        if ($baseDate === null) {
            return null;
        }

        $timezone = new \DateTimeZone('Europe/Berlin');
        $date = $baseDate->setTimezone($timezone);

        [$cutoffHour, $cutoffMinute] = array_map('intval', explode(':', $settings['cutoff']));
        $cutoffDate = $date->setTime($cutoffHour, $cutoffMinute, 0);

        if ($date > $cutoffDate) {
            $date = $date->modify('+1 day');
        }

        $workingDays = max(0, (int) $settings['workingDays']);
        while ($workingDays > 0) {
            $date = $date->modify('+1 day');
            if ($this->isBusinessDay($date)) {
                --$workingDays;
            }
        }

        while (!$this->isBusinessDay($date)) {
            $date = $date->modify('+1 day');
        }

        return $date;
    }

    private function isBusinessDay(\DateTimeImmutable $date): bool
    {
        $day = (int) $date->format('N');

        return $day <= 5;
    }
}
