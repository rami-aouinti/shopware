<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PdmsLieferzeitenClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SystemConfigService $configService,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchLieferzeiten(): array
    {
        $baseUrl = trim((string) $this->configService->get('LieferzeitenAdmin.config.pdmsApiUrl'));

        if ($baseUrl === '') {
            return [];
        }

        $token = trim((string) $this->configService->get('LieferzeitenAdmin.config.pdmsApiToken'));
        $path = trim((string) $this->configService->get('LieferzeitenAdmin.config.pdmsLieferzeitenPath'));
        $url = rtrim($baseUrl, '/');

        if ($path !== '') {
            $url .= '/' . ltrim($path, '/');
        }

        $headers = [];
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => $headers,
            ]);

            /** @var mixed $payload */
            $payload = $response->toArray();
        } catch (ExceptionInterface) {
            return [];
        }

        if (is_array($payload) && isset($payload['data']) && is_array($payload['data'])) {
            $payload = $payload['data'];
        }

        if (!is_array($payload)) {
            return [];
        }

        $lieferzeiten = [];
        foreach ($payload as $row) {
            if (!is_array($row)) {
                continue;
            }

            $lieferzeiten[] = $row;
        }

        return $lieferzeiten;
    }
}

