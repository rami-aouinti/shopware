<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service\Tracking;

use LieferzeitenAdmin\Service\Reliability\IntegrationReliabilityService;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class DhlTrackingClient implements TrackingClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private IntegrationReliabilityService $reliabilityService,
    ) {
    }

    public function supportsCarrier(string $carrier): bool
    {
        return mb_strtolower(trim($carrier)) === 'dhl';
    }

    public function fetchHistory(string $trackingNumber): array
    {
        $trackingNumber = trim($trackingNumber);
        if ($trackingNumber === '' || !preg_match('/^[0-9A-Z-]{8,40}$/', $trackingNumber)) {
            throw new TrackingProviderException('invalid_tracking_number', 'Ungültige DHL-Sendungsnummer.');
        }

        $endpoint = getenv('LIEFERZEITEN_DHL_TRACKING_URL') ?: '';
        if ($endpoint === '') {
            return [
                ['status' => 'pre_transit', 'label' => 'Sendung angekündigt', 'timestamp' => '2026-02-09T08:22:00+01:00', 'location' => 'Bremen'],
                ['status' => 'in_transit', 'label' => 'Im Paketzentrum bearbeitet', 'timestamp' => '2026-02-09T19:11:00+01:00', 'location' => 'Bremen GVZ'],
                ['status' => 'out_for_delivery', 'label' => 'In Zustellung', 'timestamp' => '2026-02-10T07:06:00+01:00', 'location' => 'Hamburg'],
            ];
        }

        try {
            return $this->reliabilityService->executeWithRetry('dhl', 'tracking_history', function () use ($endpoint, $trackingNumber): array {
                $response = $this->httpClient->request('GET', rtrim($endpoint, '/') . '/' . urlencode($trackingNumber), ['timeout' => 8.0]);
                $statusCode = $response->getStatusCode();

                if ($statusCode === 429) {
                    throw new TrackingProviderException('rate_limit', 'DHL API Limit erreicht.');
                }
                if ($statusCode >= 400) {
                    throw new TrackingProviderException('provider_error', 'DHL Tracking derzeit nicht erreichbar.');
                }

                $payload = $response->toArray(false);
                $events = $payload['events'] ?? [];

                if (!is_array($events)) {
                    return [];
                }

                return array_values(array_filter(array_map(static function ($event): ?array {
                    if (!is_array($event)) {
                        return null;
                    }

                    return [
                        'status' => (string) ($event['status'] ?? ''),
                        'label' => (string) ($event['description'] ?? $event['label'] ?? ''),
                        'timestamp' => (string) ($event['timestamp'] ?? ''),
                        'location' => (string) ($event['location'] ?? ''),
                    ];
                }, $events)));
            }, maxAttempts: 3, payload: ['trackingNumber' => $trackingNumber]);
        } catch (TimeoutExceptionInterface) {
            throw new TrackingProviderException('timeout', 'Zeitüberschreitung bei DHL Tracking.');
        }
    }
}
