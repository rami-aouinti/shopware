<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Sync\San6;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class San6Client
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SystemConfigService $config,
    ) {
    }

    /** @return array<string,mixed> */
    public function fetchByOrderNumber(string $orderNumber): array
    {
        $url = (string) $this->config->get('LieferzeitenAdmin.config.san6ApiUrl');
        if ($url === '') {
            return [];
        }

        $options = ['query' => ['orderNumber' => $orderNumber]];
        $token = (string) $this->config->get('LieferzeitenAdmin.config.san6ApiToken');
        if ($token !== '') {
            $options['headers'] = ['Authorization' => sprintf('Bearer %s', $token)];
        }

        $response = $this->httpClient->request('GET', $url, $options);
        $data = $response->toArray(false);

        return is_array($data) ? $data : [];
    }
}
