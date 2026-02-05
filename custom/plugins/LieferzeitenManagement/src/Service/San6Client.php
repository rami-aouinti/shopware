<?php declare(strict_types=1);

namespace LieferzeitenManagement\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class San6Client
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchPackages(): array
    {
        $baseUrl = $this->systemConfigService->getString('LieferzeitenManagement.config.san6ApiBaseUrl');
        $token = $this->systemConfigService->getString('LieferzeitenManagement.config.san6ApiToken');

        if ($baseUrl === '' || $token === '') {
            return [];
        }

        $response = $this->httpClient->request('GET', rtrim($baseUrl, '/') . '/packages', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ],
        ]);

        return $response->toArray(false);
    }
}
