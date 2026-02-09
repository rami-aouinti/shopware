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
    ) {
    }

    public function syncNewOrders(Context $context): void
    {
        $apiUrl = (string) $this->systemConfigService->get('ExternalOrders.config.externalOrdersApiUrl');
        if ($apiUrl === '') {
            $this->logger->warning('External Orders sync skipped: missing API URL.');

            return;
        }

        $apiToken = (string) $this->systemConfigService->get('ExternalOrders.config.externalOrdersApiToken');
        $timeout = (float) $this->systemConfigService->get('ExternalOrders.config.externalOrdersTimeout');

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
            $response = $this->httpClient->request('GET', $apiUrl, $options);
            $payload = $response->toArray(false);
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
                'url' => $this->sanitizeUrl($apiUrl),
                'status' => $statusCode,
                'durationMs' => (int) round($durationMs),
                'correlationId' => $correlationId,
            ], static fn ($value) => $value !== null));

            return;
        }

        if (!is_array($payload)) {
            $this->logger->warning('External Orders sync skipped: API response is not an array.');

            return;
        }

        $orders = $payload['orders'] ?? $payload;
        if (!is_array($orders)) {
            $this->logger->warning('External Orders sync skipped: API response does not contain orders.');

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

        if ($externalIds === []) {
            $this->logger->info('External Orders sync finished: no external IDs found.');

            return;
        }

        $existingIds = $this->fetchExistingIds($externalIds, $context);
        $upsertPayload = [];

        foreach ($orders as $order) {
            if (!is_array($order)) {
                continue;
            }

            $externalId = $this->resolveExternalId($order);
            if ($externalId === null) {
                continue;
            }

            $upsertPayload[] = [
                'id' => $existingIds[$externalId] ?? Uuid::randomHex(),
                'externalId' => $externalId,
                'payload' => $order,
            ];
        }

        if ($upsertPayload === []) {
            $this->logger->info('External Orders sync finished: nothing to upsert.');

            return;
        }

        $this->externalOrderRepository->upsert($upsertPayload, $context);
        $this->logger->info('External Orders sync finished.', [
            'total' => count($upsertPayload),
        ]);
    }

    /**
     * @param array<int, string> $externalIds
     * @return array<string, string>
     */
    private function fetchExistingIds(array $externalIds, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('externalId', $externalIds));
        $criteria->setLimit(count($externalIds));

        $result = $this->externalOrderRepository->search($criteria, $context);

        $mapping = [];
        foreach ($result->getEntities() as $entity) {
            $mapping[$entity->getExternalId()] = $entity->getId();
        }

        return $mapping;
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
