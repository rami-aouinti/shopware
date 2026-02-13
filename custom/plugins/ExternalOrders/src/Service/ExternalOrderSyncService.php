<?php declare(strict_types=1);

namespace ExternalOrders\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ExternalOrderSyncService
{
    public function __construct(
        private readonly EntityRepository $externalOrderRepository,
        private readonly HttpClientInterface $httpClient,
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger,
        private readonly TopmSan6Client $topmSan6Client,
    ) {
    }

    public function syncNewOrders(Context $context): void
    {
        $configs = $this->getChannelConfigs();
        $timeout = (float) $this->systemConfigService->get('ExternalOrders.config.externalOrdersTimeout');

        foreach ($configs as $config) {
            $apiUrl = (string) $this->systemConfigService->get($config['urlKey']);
            $san6ReadFunction = TopmSan6Client::DEFAULT_READ_FUNCTION;
            $apiToken = (string) $this->systemConfigService->get($config['tokenKey']);

            if ($config['channel'] === 'san6') {
                $san6ReadFunction = trim((string) ($this->systemConfigService->get('ExternalOrders.config.externalOrdersSan6ReadFunction') ?? ''));
                if ($san6ReadFunction === '') {
                    $san6ReadFunction = TopmSan6Client::DEFAULT_READ_FUNCTION;
                }
                $san6Url = (string) $this->systemConfigService->get('ExternalOrders.config.externalOrdersSan6BaseUrl');
                if ($san6Url !== '') {
                    $apiUrl = $this->buildSan6ApiUrl($san6Url);
                }

                $san6Auth = (string) $this->systemConfigService->get('ExternalOrders.config.externalOrdersSan6Authentifizierung');
                if ($san6Auth !== '') {
                    $apiToken = $san6Auth;
                }
            }

            if ($apiUrl === '') {
                $this->logger->warning('External Orders sync skipped: missing API URL.', [
                    'channel' => $config['channel'],
                ]);
                continue;
            }

            $this->syncChannelOrders($config['channel'], $apiUrl, $apiToken, $timeout, $context, $san6ReadFunction);
        }
    }

    /**
     * @param array<int, string> $externalIds
     * @return array<string, string>
     */
    private function fetchExistingIds(array $externalIds, Context $context): array
    {
        $mapping = [];
        $chunkSize = 500;

        foreach (array_chunk($externalIds, $chunkSize) as $chunk) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsAnyFilter('externalId', $chunk));
            $criteria->setLimit(count($chunk));

            $result = $this->externalOrderRepository->search($criteria, $context);

            foreach ($result->getEntities() as $entity) {
                $mapping[$entity->getExternalId()] = $entity->getId();
            }
        }

        return $mapping;
    }

    /**
     * @return array<int, array{channel: string, urlKey: string, tokenKey: string}>
     */
    private function getChannelConfigs(): array
    {
        return [
            [
                'channel' => 'b2b',
                'urlKey' => 'ExternalOrders.config.externalOrdersApiUrlB2b',
                'tokenKey' => 'ExternalOrders.config.externalOrdersApiTokenB2b',
            ],
            [
                'channel' => 'ebay_de',
                'urlKey' => 'ExternalOrders.config.externalOrdersApiUrlEbayDe',
                'tokenKey' => 'ExternalOrders.config.externalOrdersApiTokenEbayDe',
            ],
            [
                'channel' => 'kaufland',
                'urlKey' => 'ExternalOrders.config.externalOrdersApiUrlKaufland',
                'tokenKey' => 'ExternalOrders.config.externalOrdersApiTokenKaufland',
            ],
            [
                'channel' => 'ebay_at',
                'urlKey' => 'ExternalOrders.config.externalOrdersApiUrlEbayAt',
                'tokenKey' => 'ExternalOrders.config.externalOrdersApiTokenEbayAt',
            ],
            [
                'channel' => 'zonami',
                'urlKey' => 'ExternalOrders.config.externalOrdersApiUrlZonami',
                'tokenKey' => 'ExternalOrders.config.externalOrdersApiTokenZonami',
            ],
            [
                'channel' => 'peg',
                'urlKey' => 'ExternalOrders.config.externalOrdersApiUrlPeg',
                'tokenKey' => 'ExternalOrders.config.externalOrdersApiTokenPeg',
            ],
            [
                'channel' => 'bezb',
                'urlKey' => 'ExternalOrders.config.externalOrdersApiUrlBezb',
                'tokenKey' => 'ExternalOrders.config.externalOrdersApiTokenBezb',
            ],
            [
                'channel' => 'san6',
                'urlKey' => 'ExternalOrders.config.externalOrdersSan6BaseUrl',
                'tokenKey' => 'ExternalOrders.config.externalOrdersSan6Authentifizierung',
            ],
        ];
    }


    private function buildSan6ApiUrl(string $baseUrl): string
    {
        $query = array_filter([
            'company' => (string) $this->systemConfigService->get('ExternalOrders.config.externalOrdersSan6Company'),
            'product' => (string) $this->systemConfigService->get('ExternalOrders.config.externalOrdersSan6Product'),
            'mandant' => (string) $this->systemConfigService->get('ExternalOrders.config.externalOrdersSan6Mandant'),
            'sys' => (string) $this->systemConfigService->get('ExternalOrders.config.externalOrdersSan6Sys'),
            'authentifizierung' => (string) $this->systemConfigService->get('ExternalOrders.config.externalOrdersSan6Authentifizierung'),
        ], static fn (string $value): bool => $value !== '');

        if ($query === []) {
            return $baseUrl;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl . $separator . http_build_query($query);
    }

    private function syncChannelOrders(
        string $channel,
        string $apiUrl,
        string $apiToken,
        float $timeout,
        Context $context,
        ?string $san6ReadFunction = null
    ): void {
        $options = [];
        if ($apiToken !== '') {
            $options['headers'] = [
                'Authorization' => sprintf('Bearer %s', $apiToken),
            ];
        }

        if ($timeout > 0) {
            $options['timeout'] = $timeout;
        }

        $response = null;
        $startTime = microtime(true);
        try {
            if ($channel === 'san6') {
                $payload = $this->topmSan6Client->fetchOrders($apiUrl, $apiToken, $timeout, $san6ReadFunction ?? TopmSan6Client::DEFAULT_READ_FUNCTION);
            } else {
                $response = $this->httpClient->request('GET', $apiUrl, $options);
                $payload = $response->toArray(false);
            }
        } catch (\InvalidArgumentException $exception) {
            $this->logger->error('External Orders sync skipped: invalid SAN6 config.', [
                'channel' => $channel,
                'url' => $this->sanitizeUrl($apiUrl),
                'error' => $exception->getMessage(),
            ]);

            return;
        } catch (ExceptionInterface $exception) {
            $durationMs = (microtime(true) - $startTime) * 1000;
            $statusCode = null;
            $correlationId = null;

            if ($exception instanceof HttpExceptionInterface) {
                $response = $exception->getResponse();
            }

            if ($response instanceof ResponseInterface) {
                $statusCode = $response->getStatusCode();
                $correlationId = $this->resolveCorrelationId($response);
            }

            $this->logger->error('External Orders sync failed while calling API.', array_filter([
                'channel' => $channel,
                'url' => $this->sanitizeUrl($apiUrl),
                'status' => $statusCode,
                'durationMs' => (int) round($durationMs),
                'correlationId' => $correlationId,
            ], static fn ($value) => $value !== null));

            return;
        }

        if (!is_array($payload)) {
            $this->logger->warning('External Orders sync skipped: API response is not an array.', [
                'channel' => $channel,
            ]);

            return;
        }

        $orders = $payload['orders'] ?? $payload;
        if (!is_array($orders)) {
            $this->logger->warning('External Orders sync skipped: API response does not contain orders.', [
                'channel' => $channel,
            ]);

            return;
        }

        $externalIds = [];
        foreach ($orders as $order) {
            if (!is_array($order)) {
                continue;
            }

            $externalId = $this->resolveExternalId($order);
            if ($externalId !== null) {
                $externalIds[] = $externalId;
            }
        }

        $externalIds = array_values(array_unique($externalIds));

        if ($externalIds === []) {
            $this->logger->info('External Orders sync finished: no external IDs found.', [
                'channel' => $channel,
            ]);

            return;
        }

        $existingIds = $this->fetchExistingIds($externalIds, $context);
        $insertPayload = [];

        foreach ($orders as $order) {
            if (!is_array($order)) {
                continue;
            }

            $externalId = $this->resolveExternalId($order);
            if ($externalId === null || isset($existingIds[$externalId])) {
                continue;
            }

            $order['channel'] = $order['channel'] ?? $channel;

            $insertPayload[] = [
                'id' => Uuid::randomHex(),
                'externalId' => $externalId,
                'payload' => $order,
            ];
        }

        if ($insertPayload === []) {
            $this->logger->info('External Orders sync finished: no new orders found.', [
                'channel' => $channel,
            ]);

            return;
        }

        $this->externalOrderRepository->upsert($insertPayload, $context);
        $this->logger->info('External Orders sync finished.', [
            'channel' => $channel,
            'total' => count($insertPayload),
        ]);
    }

    /**
     * @param array<mixed> $order
     */
    private function resolveExternalId(array $order): ?string
    {
        $externalId = $order['externalId'] ?? $order['id'] ?? $order['orderNumber'] ?? null;

        if (!is_string($externalId) || $externalId === '') {
            return null;
        }

        return $externalId;
    }

    private function resolveCorrelationId(ResponseInterface $response): ?string
    {
        $headers = array_change_key_case($response->getHeaders(false), CASE_LOWER);

        foreach (['x-correlation-id', 'correlation-id', 'x-request-id'] as $headerName) {
            if (!isset($headers[$headerName][0])) {
                continue;
            }

            $value = trim((string) $headers[$headerName][0]);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function sanitizeUrl(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        $safeQuery = '';
        if (isset($parts['query']) && $parts['query'] !== '') {
            parse_str($parts['query'], $queryParams);
            foreach ($queryParams as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }

                if (preg_match('/(token|access|key|secret|signature|sig|password|auth)/i', $key) === 1) {
                    $queryParams[$key] = '***';
                }
            }

            $safeQuery = http_build_query($queryParams);
        }

        $safeUrl = '';
        if (isset($parts['scheme'])) {
            $safeUrl .= $parts['scheme'] . '://';
        }
        if (isset($parts['user'])) {
            $safeUrl .= $parts['user'];
            if (isset($parts['pass'])) {
                $safeUrl .= ':***';
            }
            $safeUrl .= '@';
        }
        if (isset($parts['host'])) {
            $safeUrl .= $parts['host'];
        }
        if (isset($parts['port'])) {
            $safeUrl .= ':' . $parts['port'];
        }
        $safeUrl .= $parts['path'] ?? '';
        if ($safeQuery !== '') {
            $safeUrl .= '?' . $safeQuery;
        }
        if (isset($parts['fragment'])) {
            $safeUrl .= '#' . $parts['fragment'];
        }

        return $safeUrl;
    }
}
