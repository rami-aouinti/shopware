<?php declare(strict_types=1);

namespace LieferzeitenManagement\Service;

class TrackingStatusInterpreter
{
    public const STATUS_COMPLETED = 'abgeschlossen';
    public const STATUS_PENDING = 'nicht abgeschlossen';

    /**
     * @param array<int, array<string, mixed>> $events
     */
    public function interpret(array $events, ?string $fallbackStatus, ?string $fallbackDeliveredAt): TrackingStatusResult
    {
        [$hasCompletedEvent, $completedAt] = $this->resolveCompletedEventData($events);

        if ($hasCompletedEvent) {
            return new TrackingStatusResult(self::STATUS_COMPLETED, $completedAt ?? $fallbackDeliveredAt);
        }

        if ($events !== []) {
            return new TrackingStatusResult(self::STATUS_PENDING, null);
        }

        if ($fallbackStatus !== null && $this->isCompletedStatus($fallbackStatus)) {
            return new TrackingStatusResult(self::STATUS_COMPLETED, $fallbackDeliveredAt);
        }

        if ($fallbackDeliveredAt !== null) {
            return new TrackingStatusResult(self::STATUS_COMPLETED, $fallbackDeliveredAt);
        }

        return new TrackingStatusResult(self::STATUS_PENDING, null);
    }

    /**
     * @param array<int, array<string, mixed>> $events
     *
     * @return array{bool, ?string}
     */
    private function resolveCompletedEventData(array $events): array
    {
        $hasCompletedEvent = false;
        $latest = null;

        foreach ($events as $event) {
            if (!$this->eventIndicatesCompletion($event)) {
                continue;
            }

            $hasCompletedEvent = true;
            $occurredAt = $event['occurredAt'] ?? null;

            if (!is_string($occurredAt) || $occurredAt === '') {
                continue;
            }

            $parsed = date_create_immutable($occurredAt);

            if (!$parsed) {
                continue;
            }

            if ($latest === null || $parsed > $latest) {
                $latest = $parsed;
            }
        }

        return [$hasCompletedEvent, $latest?->format(DATE_ATOM)];
    }

    /**
     * @param array<string, mixed> $event
     */
    private function eventIndicatesCompletion(array $event): bool
    {
        $status = (string) ($event['status'] ?? '');
        $description = (string) ($event['description'] ?? '');

        return $this->isCompletedStatus(trim($status . ' ' . $description));
    }

    private function isCompletedStatus(string $value): bool
    {
        $normalized = mb_strtolower($value);

        foreach ([
            'abgeschlossen',
            'zugestellt',
            'ausgeliefert',
            'delivered',
            'delivery completed',
            'completed',
            'success',
            'erfolgreich',
        ] as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
