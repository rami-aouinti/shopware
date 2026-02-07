<?php declare(strict_types=1);

namespace LieferzeitenManagement\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TrackingClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchTrackingEvents(string $trackingNumber): array
    {
        $baseUrl = $this->systemConfigService->getString('LieferzeitenManagement.config.trackingApiBaseUrl');
        $token = $this->systemConfigService->getString('LieferzeitenManagement.config.trackingApiToken');

        if ($baseUrl === '' || $token === '') {
            return [];
        }

        $response = $this->httpClient->request('GET', rtrim($baseUrl, '/') . '/tracking/' . urlencode($trackingNumber), [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ],
        ]);

        return $response->toArray(false);
    }
}
