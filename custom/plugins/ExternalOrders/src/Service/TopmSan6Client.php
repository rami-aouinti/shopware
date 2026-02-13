<?php declare(strict_types=1);

namespace ExternalOrders\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TopmSan6Client
{
    public const DEFAULT_READ_FUNCTION = 'API-AUFTRAEGE';
    public const DEFAULT_WRITE_FUNCTION = 'API-AUFTRAGNEU2';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly TopmSan6OrderMapper $orderMapper,
    ) {
    }

    /**
     * @return array{orders: array<int, array<string, mixed>>}
     */
    public function fetchOrders(string $apiUrl, string $apiToken, float $timeout, ?string $readFunction = null): array
    {
        if (!$this->hasRequiredSan6Parameters($apiUrl, $apiToken)) {
            return ['orders' => []];
        }

        $url = $this->buildTopmUrl($apiUrl, $readFunction ?? self::DEFAULT_READ_FUNCTION, $apiToken);
        $options = $this->buildTimeoutOptions($timeout);

        try {
            $response = $this->httpClient->request('GET', $url, $options);
            $rawXml = $response->getContent(false);
        } catch (ExceptionInterface $exception) {
            $this->logger->error('TopM san6 read request failed.', [
                'url' => $this->sanitizeUrl($url),
                'error' => $exception->getMessage(),
            ]);

            return ['orders' => []];
        }

        return ['orders' => $this->mapXmlOrders($rawXml)];
    }

    public function sendByFileTransferUrl(string $apiUrl, string $apiToken, string $fileTransferUrl, float $timeout, ?string $writeFunction = null): string
    {
        if (!$this->hasRequiredSan6Parameters($apiUrl, $apiToken)) {
            return '';
        }

        $url = $this->buildTopmUrl($apiUrl, $writeFunction ?? self::DEFAULT_WRITE_FUNCTION, $apiToken, [
            // TopM expects this exact lowercase query parameter name.
            'filetransferurl' => $fileTransferUrl,
        ]);
        $options = $this->buildTimeoutOptions($timeout);

        $response = $this->httpClient->request('GET', $url, $options);

        return $response->getContent(false);
    }

    public function sendByPostXml(string $apiUrl, string $apiToken, string $xmlBody, float $timeout, ?string $writeFunction = null): string
    {
        if (!$this->hasRequiredSan6Parameters($apiUrl, $apiToken)) {
            return '';
        }

        $url = $this->buildTopmUrl($apiUrl, $writeFunction ?? self::DEFAULT_WRITE_FUNCTION, $apiToken);
        $options = $this->buildTimeoutOptions($timeout);
        $options['headers']['Content-Type'] = 'application/xml';
        $options['body'] = $xmlBody;

        $response = $this->httpClient->request('POST', $url, $options);

        return $response->getContent(false);
    }

    /**
     * @param array<string, mixed> $extraParams
     */
    private function buildTopmUrl(string $apiUrl, string $funktion, string $apiToken, array $extraParams = []): string
    {
        $parts = parse_url($apiUrl);
        if ($parts === false) {
            return $apiUrl;
        }

        $query = [];
        if (isset($parts['query']) && $parts['query'] !== '') {
            parse_str($parts['query'], $query);
        }

        $requiredParams = [
            'ssid' => ($query['ssid'] ?? '') !== '' ? (string) $query['ssid'] : 'nosession',
            'company' => $query['company'] ?? '',
            'product' => $query['product'] ?? '',
            'mandant' => $query['mandant'] ?? '',
            'sys' => $query['sys'] ?? '',
            'authentifizierung' => $query['authentifizierung'] ?? $apiToken,
            'funktion' => $funktion,
        ];

        $query = array_merge($query, $requiredParams, $extraParams);

        $baseUrl = '';
        if (isset($parts['scheme'])) {
            $baseUrl .= $parts['scheme'] . '://';
        }
        if (isset($parts['user'])) {
            $baseUrl .= $parts['user'];
            if (isset($parts['pass'])) {
                $baseUrl .= ':' . $parts['pass'];
            }
            $baseUrl .= '@';
        }
        if (isset($parts['host'])) {
            $baseUrl .= $parts['host'];
        }
        if (isset($parts['port'])) {
            $baseUrl .= ':' . $parts['port'];
        }
        $baseUrl .= $parts['path'] ?? '';

        return $baseUrl . '?' . http_build_query($query);
    }

    private function hasRequiredSan6Parameters(string $apiUrl, string $apiToken): bool
    {
        $parts = parse_url($apiUrl);
        if ($parts === false) {
            $this->logger->error('TopM san6 request skipped: invalid base URL.');

            return false;
        }

        $query = [];
        if (isset($parts['query']) && $parts['query'] !== '') {
            parse_str($parts['query'], $query);
        }

        $requiredParams = [
            'company' => (string) ($query['company'] ?? ''),
            'product' => (string) ($query['product'] ?? ''),
            'mandant' => (string) ($query['mandant'] ?? ''),
            'sys' => (string) ($query['sys'] ?? ''),
            'authentifizierung' => (string) ($query['authentifizierung'] ?? $apiToken),
        ];

        $missingParams = array_keys(array_filter($requiredParams, static fn (string $value): bool => $value === ''));
        if ($missingParams !== []) {
            $this->logger->error('TopM san6 request skipped: incomplete SAN6 config.', [
                'url' => $this->sanitizeUrl($apiUrl),
                'missing' => $missingParams,
            ]);

            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTimeoutOptions(float $timeout): array
    {
        if ($timeout <= 0) {
            return [];
        }

        return ['timeout' => $timeout];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapXmlOrders(string $xmlContent): array
    {
        if (trim($xmlContent) === '') {
            return [];
        }

        $previousInternalErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent, \SimpleXMLElement::class, LIBXML_NOCDATA);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previousInternalErrors);

        if ($xml === false) {
            $this->logger->error('TopM san6 XML parsing failed.', [
                'errors' => array_map(static fn (\LibXMLError $error): string => trim($error->message), $errors),
            ]);

            return [];
        }

        $data = $this->xmlNodeToArray($xml);
        $ordersData = $this->extractOrders($data);
        $orders = [];

        foreach ($ordersData as $orderData) {
            if (!is_array($orderData)) {
                continue;
            }

            $mappedOrder = $this->orderMapper->mapOrder($orderData);
            $externalId = $this->resolveExternalId($mappedOrder);

            $orders[] = array_merge($mappedOrder, [
                'externalId' => $externalId,
                'orderNumber' => $mappedOrder['orderNumber'] ?? $externalId,
                'channel' => 'san6',
            ]);
        }

        return $orders;
    }

    /**
     * @param mixed $node
     * @return mixed
     */
    private function xmlNodeToArray($node)
    {
        if (!($node instanceof \SimpleXMLElement)) {
            return $node;
        }

        $result = [];

        foreach ($node->children() as $childName => $childNode) {
            $value = $this->xmlNodeToArray($childNode);
            if (array_key_exists($childName, $result)) {
                if (!is_array($result[$childName]) || !array_is_list($result[$childName])) {
                    $result[$childName] = [$result[$childName]];
                }

                $result[$childName][] = $value;
                continue;
            }

            $result[$childName] = $value;
        }

        if ($result !== []) {
            return $result;
        }

        return trim((string) $node);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array<string, mixed>>
     */
    private function extractOrders(array $data): array
    {
        foreach (['orders', 'order', 'auftraege', 'auftraege_liste', 'auftrag'] as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];
            if (!is_array($value)) {
                continue;
            }

            if (array_is_list($value)) {
                /** @var array<int, array<string, mixed>> $value */
                return $value;
            }

            if (isset($value['auftrag']) && is_array($value['auftrag'])) {
                $auftrag = $value['auftrag'];

                if (array_is_list($auftrag)) {
                    /** @var array<int, array<string, mixed>> $auftrag */
                    return $auftrag;
                }

                return [$auftrag];
            }

            return [$value];
        }

        if (isset($data['response']) && is_array($data['response'])) {
            return $this->extractOrders($data['response']);
        }

        return [];
    }

    /**
     * @param array<string, mixed> $order
     */
    private function resolveExternalId(array $order): string
    {
        foreach (['externalId', 'id', 'orderNumber', 'auftragsnummer', 'auftragnummer', 'auftrags_nr'] as $key) {
            $value = $order[$key] ?? null;
            if (is_scalar($value)) {
                $candidate = trim((string) $value);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        return '';
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

                if (preg_match('/(token|access|key|secret|signature|sig|password|auth|authentifizierung)/i', $key) === 1) {
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
