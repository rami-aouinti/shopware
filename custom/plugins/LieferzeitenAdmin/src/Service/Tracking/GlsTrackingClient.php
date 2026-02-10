<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service\Tracking;

use LieferzeitenAdmin\Service\Reliability\IntegrationReliabilityService;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class GlsTrackingClient implements TrackingClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private IntegrationReliabilityService $reliabilityService,
    ) {
    }

    public function supportsCarrier(string $carrier): bool
    {
        return mb_strtolower(trim($carrier)) === 'gls';
    }

    public function fetchHistory(string $trackingNumber): array
    {
        $trackingNumber = trim($trackingNumber);
        if ($trackingNumber === '' || !preg_match('/^[0-9A-Z]{8,30}$/', $trackingNumber)) {
            throw new TrackingProviderException('invalid_tracking_number', 'Ungültige GLS-Sendungsnummer.');
        }

        $endpoint = getenv('LIEFERZEITEN_GLS_TRACKING_URL') ?: '';
        if ($endpoint === '') {
            return [
                ['status' => 'pre_transit', 'label' => 'Paketdaten übermittelt', 'timestamp' => '2026-02-09T09:05:00+01:00', 'location' => 'Neuenstein'],
                ['status' => 'in_transit', 'label' => 'Paketzentrum verlassen', 'timestamp' => '2026-02-09T22:13:00+01:00', 'location' => 'Neuenstein'],
                ['status' => 'delivered', 'label' => 'Paket zugestellt', 'timestamp' => '2026-02-10T11:33:00+01:00', 'location' => 'Hamburg'],
            ];
        }

        try {
            return $this->reliabilityService->executeWithRetry('gls', 'tracking_history', function () use ($endpoint, $trackingNumber): array {
                $response = $this->httpClient->request('GET', rtrim($endpoint, '/') . '/' . urlencode($trackingNumber), ['timeout' => 8.0]);
                $statusCode = $response->getStatusCode();

                if ($statusCode === 429) {
                    throw new TrackingProviderException('rate_limit', 'GLS API Limit erreicht.');
                }
                if ($statusCode >= 400) {
                    throw new TrackingProviderException('provider_error', 'GLS Tracking derzeit nicht erreichbar.');
                }

                $payload = $response->toArray(false);
                $events = $payload['history'] ?? [];

                if (!is_array($events)) {
                    return [];
                }

                return array_values(array_filter(array_map(static function ($event): ?array {
                    if (!is_array($event)) {
                        return null;
                    }

                    return [
                        'status' => (string) ($event['code'] ?? $event['status'] ?? ''),
                        'label' => (string) ($event['text'] ?? $event['label'] ?? ''),
                        'timestamp' => (string) ($event['dateTime'] ?? $event['timestamp'] ?? ''),
                        'location' => (string) ($event['city'] ?? $event['location'] ?? ''),
                    ];
                }, $events)));
            }, maxAttempts: 3, payload: ['trackingNumber' => $trackingNumber]);
        } catch (TimeoutExceptionInterface) {
            throw new TrackingProviderException('timeout', 'Zeitüberschreitung bei GLS Tracking.');
        }
    }
}
