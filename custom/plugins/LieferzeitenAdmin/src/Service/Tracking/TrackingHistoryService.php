<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service\Tracking;

readonly class TrackingHistoryService
{
    /**
     * @param iterable<TrackingClientInterface> $trackingClients
     */
    public function __construct(private iterable $trackingClients)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchHistory(string $carrier, string $trackingNumber): array
    {
        $client = $this->resolveClient($carrier);

        if ($client === null) {
            return [
                'ok' => false,
                'errorCode' => 'carrier_not_supported',
                'message' => 'Der Versanddienstleister wird nicht unterstützt.',
                'carrier' => mb_strtoupper($carrier),
                'trackingNumber' => $trackingNumber,
            ];
        }

        try {
            $events = $client->fetchHistory($trackingNumber);

            return [
                'ok' => true,
                'carrier' => mb_strtoupper($carrier),
                'trackingNumber' => $trackingNumber,
                'events' => $this->normalizeEvents($events),
            ];
        } catch (TrackingProviderException $exception) {
            return [
                'ok' => false,
                'errorCode' => $exception->getErrorCode(),
                'message' => $exception->getMessage(),
                'carrier' => mb_strtoupper($carrier),
                'trackingNumber' => $trackingNumber,
            ];
        }
    }

    /**
     * @param array<int, array<string, mixed>> $events
     *
     * @return array<int, array<string, string>>
     */
    private function normalizeEvents(array $events): array
    {
        $normalized = [];

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $normalized[] = [
                'status' => $this->normalizeStatus((string) ($event['status'] ?? 'unknown')),
                'label' => (string) ($event['label'] ?? ''),
                'timestamp' => (string) ($event['timestamp'] ?? ''),
                'location' => (string) ($event['location'] ?? ''),
            ];
        }

        usort($normalized, static fn (array $left, array $right): int => strcmp($right['timestamp'], $left['timestamp']));

        return $normalized;
    }

    private function normalizeStatus(string $status): string
    {
        $value = mb_strtolower(trim($status));

        return match (true) {
            str_contains($value, 'deliver') || str_contains($value, 'zugestellt') => 'delivered',
            str_contains($value, 'out_for_delivery') || str_contains($value, 'zustellung') => 'out_for_delivery',
            str_contains($value, 'transit') || str_contains($value, 'unterwegs') => 'in_transit',
            str_contains($value, 'pre') || str_contains($value, 'angekündigt') => 'pre_transit',
            default => 'unknown',
        };
    }

    private function resolveClient(string $carrier): ?TrackingClientInterface
    {
        foreach ($this->trackingClients as $trackingClient) {
            if ($trackingClient->supportsCarrier($carrier)) {
                return $trackingClient;
            }
        }

        return null;
    }
}
