<?php declare(strict_types=1);

namespace ExternalOrders\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TopmSan6OrderExportService
{
    private const MAX_RETRIES = 5;

    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly TopmSan6Client $topmSan6Client,
        private readonly SystemConfigService $systemConfigService,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function exportOrder(string $orderId, Context $context, bool $isRetry = false): array
    {
        $order = $this->fetchOrder($orderId, $context);
        if ($order === null) {
            throw new \RuntimeException(sprintf('Order %s not found.', $orderId));
        }

        $xml = $this->buildAuftragNeu2Xml($order);
        $exportId = Uuid::randomHex();
        $strategy = (string) ($this->systemConfigService->get('ExternalOrders.config.externalOrdersSan6SendStrategy') ?? 'filetransferurl');
        $apiUrl = $this->buildSan6ApiUrl((string) $this->systemConfigService->get('ExternalOrders.config.externalOrdersSan6BaseUrl'));
        $apiToken = (string) $this->systemConfigService->get('ExternalOrders.config.externalOrdersSan6Authentifizierung');
        $timeout = (float) ($this->systemConfigService->get('ExternalOrders.config.externalOrdersTimeout') ?? 2.5);
        $writeFunction = trim((string) ($this->systemConfigService->get('ExternalOrders.config.externalOrdersSan6WriteFunction') ?? ''));
        if ($writeFunction === '') {
            $writeFunction = TopmSan6Client::DEFAULT_WRITE_FUNCTION;
        }
        $correlationId = Uuid::randomHex();

        $this->createState($exportId, $orderId, $xml, $strategy, $correlationId, $isRetry);

        try {
            if ($strategy === 'filetransferurl') {
                $signedUrl = $this->generateSignedFileTransferUrl($exportId);
                $responseXml = $this->topmSan6Client->sendByFileTransferUrl($apiUrl, $apiToken, $signedUrl, $timeout, $writeFunction);
            } else {
                $responseXml = $this->topmSan6Client->sendByPostXml($apiUrl, $apiToken, $xml, $timeout, $writeFunction);
            }

            [$code, $message] = $this->extractTopmResponse($responseXml);
            $status = ($code !== null && (int) $code === 0) ? 'sent' : 'failed';

            $this->connection->update('external_order_export', [
                'status' => $status,
                'response_code' => $code,
                'response_message' => $message,
                'response_xml' => $responseXml,
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
            ], ['id' => Uuid::fromHexToBytes($exportId)]);

            $this->logger->info('TopM order export response received.', [
                'orderId' => $orderId,
                'exportId' => $exportId,
                'correlationId' => $correlationId,
                'status' => $status,
                'responseCode' => $code,
                'responseMessage' => $message,
            ]);

            if ($status === 'failed') {
                $this->scheduleRetry($exportId, 'TopM response indicates failure');
            }

            return [
                'exportId' => $exportId,
                'orderId' => $orderId,
                'status' => $status,
                'responseCode' => $code,
                'responseMessage' => $message,
                'correlationId' => $correlationId,
            ];
        } catch (\Throwable $exception) {
            $this->scheduleRetry($exportId, $exception->getMessage());

            $this->logger->error('TopM order export failed.', [
                'orderId' => $orderId,
                'exportId' => $exportId,
                'correlationId' => $correlationId,
                'error' => $this->maskSecrets($exception->getMessage()),
            ]);

            throw $exception;
        }
    }

    public function serveSignedExportXml(string $token): ?string
    {
        $payload = $this->parseSignedToken($token);
        if ($payload === null) {
            return null;
        }

        [$exportId, $expiresAt] = $payload;
        if ($expiresAt < time()) {
            return null;
        }

        $xml = $this->connection->fetchOne(
            'SELECT request_xml FROM external_order_export WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($exportId)]
        );

        return is_string($xml) && $xml !== '' ? $xml : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLatestExportStatus(string $orderId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT HEX(id) as id, status, attempts, response_code, response_message, correlation_id, next_retry_at, updated_at
             FROM external_order_export
             WHERE order_id = :orderId
             ORDER BY created_at DESC
             LIMIT 1',
            ['orderId' => Uuid::fromHexToBytes($orderId)]
        );

        if (!is_array($row)) {
            return null;
        }

        return [
            'exportId' => strtolower((string) ($row['id'] ?? '')),
            'status' => (string) ($row['status'] ?? ''),
            'attempts' => (int) ($row['attempts'] ?? 0),
            'responseCode' => $row['response_code'] !== null ? (int) $row['response_code'] : null,
            'responseMessage' => (string) ($row['response_message'] ?? ''),
            'correlationId' => (string) ($row['correlation_id'] ?? ''),
            'nextRetryAt' => $row['next_retry_at'],
            'updatedAt' => $row['updated_at'],
        ];
    }

    public function processRetries(Context $context): int
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT HEX(id) as id, HEX(order_id) as order_id FROM external_order_export
             WHERE status = :status AND next_retry_at IS NOT NULL AND next_retry_at <= NOW(3) AND attempts < :max
             ORDER BY next_retry_at ASC LIMIT 20',
            ['status' => 'retry_scheduled', 'max' => self::MAX_RETRIES]
        );

        $processed = 0;
        foreach ($rows as $row) {
            $orderId = strtolower((string) ($row['order_id'] ?? ''));
            if ($orderId === '') {
                continue;
            }

            try {
                $this->exportOrder($orderId, $context, true);
                $processed++;
            } catch (\Throwable) {
            }
        }

        return $processed;
    }

    private function createState(string $exportId, string $orderId, string $requestXml, string $strategy, string $correlationId, bool $isRetry): void
    {
        $this->connection->insert('external_order_export', [
            'id' => Uuid::fromHexToBytes($exportId),
            'order_id' => Uuid::fromHexToBytes($orderId),
            'status' => 'processing',
            'strategy' => $strategy,
            'attempts' => $isRetry ? 1 : 0,
            'request_xml' => $requestXml,
            'correlation_id' => $correlationId,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);
    }

    private function scheduleRetry(string $exportId, string $error): void
    {
        $attempts = (int) $this->connection->fetchOne(
            'SELECT attempts FROM external_order_export WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($exportId)]
        );

        $nextStatus = $attempts + 1 >= self::MAX_RETRIES ? 'failed_permanent' : 'retry_scheduled';
        $nextRetryAt = $nextStatus === 'retry_scheduled'
            ? (new \DateTimeImmutable(sprintf('+%d minutes', max(1, ($attempts + 1) * 5))))->format('Y-m-d H:i:s.v')
            : null;

        $this->connection->update('external_order_export', [
            'status' => $nextStatus,
            'attempts' => $attempts + 1,
            'last_error' => mb_substr($this->maskSecrets($error), 0, 2000),
            'next_retry_at' => $nextRetryAt,
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ], ['id' => Uuid::fromHexToBytes($exportId)]);
    }

    private function generateSignedFileTransferUrl(string $exportId): string
    {
        $expiresAt = time() + 600;
        $secret = (string) ($_ENV['APP_SECRET'] ?? 'external-orders-secret');
        $signature = hash_hmac('sha256', $exportId . '|' . $expiresAt, $secret);
        $token = rtrim(strtr(base64_encode($exportId . '|' . $expiresAt . '|' . $signature), '+/', '-_'), '=');

        return $this->resolveBaseUrl() . $this->urlGenerator->generate('api.external-orders.export.file-transfer', ['token' => $token]);
    }

    /** @return array{0:string,1:int}|null */
    private function parseSignedToken(string $token): ?array
    {
        $normalized = strtr($token, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);
        if (!is_string($decoded) || $decoded === '') {
            return null;
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 3) {
            return null;
        }

        [$exportId, $expiresAt, $signature] = $parts;
        if (!Uuid::isValid($exportId) || !is_numeric($expiresAt)) {
            return null;
        }

        $secret = (string) ($_ENV['APP_SECRET'] ?? 'external-orders-secret');
        $expected = hash_hmac('sha256', $exportId . '|' . $expiresAt, $secret);

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        return [$exportId, (int) $expiresAt];
    }

    /** @return array{0:int|null,1:string|null} */
    private function extractTopmResponse(string $responseXml): array
    {
        $xml = @simplexml_load_string($responseXml);
        if ($xml === false) {
            return [null, 'Unparseable XML response'];
        }

        $array = json_decode((string) json_encode($xml), true);
        if (!is_array($array)) {
            return [null, null];
        }

        $code = $this->extractFirstScalar($array, ['response_code', 'returnCode', 'code', 'statusCode', 'ret']);
        $message = $this->extractFirstScalar($array, ['response_message', 'message', 'returnMessage', 'statusMessage', 'msg']);

        return [$code !== null ? (int) $code : null, $message !== null ? (string) $message : null];
    }

    private function extractFirstScalar(array $payload, array $keys): string|int|float|bool|null
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];
            if (is_scalar($value)) {
                return $value;
            }
        }

        foreach ($payload as $value) {
            if (!is_array($value)) {
                continue;
            }

            $found = $this->extractFirstScalar($value, $keys);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function resolveBaseUrl(): string
    {
        $base = (string) ($this->systemConfigService->get('core.basicInformation.shopwareUrl') ?? '');
        if ($base === '') {
            $base = (string) ($_ENV['APP_URL'] ?? 'http://localhost');
        }

        return rtrim($base, '/');
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

    private function fetchOrder(string $orderId, Context $context): ?OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('billingAddress.country');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('transactions.paymentMethod');
        $criteria->addAssociation('orderCustomer.customer');

        $order = $this->orderRepository->search($criteria, $context)->first();

        return $order instanceof OrderEntity ? $order : null;
    }

    private function buildAuftragNeu2Xml(OrderEntity $order): string
    {
        $billing = $order->getBillingAddress();
        $delivery = $order->getDeliveries()?->first();
        $shipping = $delivery?->getShippingOrderAddress();

        $xml = new \SimpleXMLElement('<AuftragNeu2/>');
        $xml->addChild('Referenz', $this->xmlValue($order->getOrderNumber() ?? ''));
        $xml->addChild('Datum', $this->formatXmlDate($order->getOrderDateTime()));

        $kunde = $xml->addChild('Kunde');
        $kunde->addChild('Firma', $this->xmlValue((string) ($billing?->getCompany() ?? '')));
        $kunde->addChild('Vorname', $this->xmlValue((string) ($billing?->getFirstName() ?? '')));
        $kunde->addChild('Nachname', $this->xmlValue((string) ($billing?->getLastName() ?? '')));
        $kunde->addChild('Strasse', $this->xmlValue((string) ($billing?->getStreet() ?? '')));
        $kunde->addChild('PLZ', $this->xmlValue((string) ($billing?->getZipcode() ?? '')));
        $kunde->addChild('Ort', $this->xmlValue((string) ($billing?->getCity() ?? '')));
        $kunde->addChild('Email', $this->xmlValue((string) ($order->getOrderCustomer()?->getEmail() ?? '')));

        $lieferadresse = $xml->addChild('Lieferadresse');
        $lieferadresse->addChild('Firma', $this->xmlValue((string) ($shipping?->getCompany() ?? $billing?->getCompany() ?? '')));
        $lieferadresse->addChild('Vorname', $this->xmlValue((string) ($shipping?->getFirstName() ?? $billing?->getFirstName() ?? '')));
        $lieferadresse->addChild('Nachname', $this->xmlValue((string) ($shipping?->getLastName() ?? $billing?->getLastName() ?? '')));
        $lieferadresse->addChild('Strasse', $this->xmlValue((string) ($shipping?->getStreet() ?? $billing?->getStreet() ?? '')));
        $lieferadresse->addChild('PLZ', $this->xmlValue((string) ($shipping?->getZipcode() ?? $billing?->getZipcode() ?? '')));
        $lieferadresse->addChild('Ort', $this->xmlValue((string) ($shipping?->getCity() ?? $billing?->getCity() ?? '')));

        $positionen = $xml->addChild('Positionen');
        foreach ($order->getLineItems() ?? [] as $lineItem) {
            if ($lineItem->getType() !== 'product') {
                continue;
            }

            $position = $positionen->addChild('Position');
            $position->addChild('Referenz', $this->xmlValue((string) ($lineItem->getReferencedId() ?? $lineItem->getIdentifier())));
            $position->addChild('Bezeichnung', $this->xmlValue((string) $lineItem->getLabel()));
            $position->addChild('Gr', $this->normalizeVariantGr($lineItem->getPayload()));
            $position->addChild('Menge', (string) $lineItem->getQuantity());
            $position->addChild('Preis', number_format((float) ($lineItem->getPrice()?->getUnitPrice() ?? 0.0), 2, '.', ''));
        }

        $attachmentsNode = $xml->addChild('Anlagen');
        $customFields = $order->getCustomFields() ?? [];
        $attachments = $customFields['externalOrderAttachments'] ?? [];
        if (is_array($attachments)) {
            foreach ($attachments as $index => $attachment) {
                if (!is_string($attachment) || trim($attachment) === '') {
                    continue;
                }

                $item = $attachmentsNode->addChild('Anlage');
                $item->addChild('Dateiname', sprintf('attachment-%d.txt', $index + 1));
                $item->addChild('Datei', base64_encode(trim($attachment)));
            }
        }

        $this->assertXmlHasMandatoryNodes($xml, ['Referenz', 'Datum', 'Kunde', 'Positionen', 'Anlagen']);

        return $xml->asXML() ?: '';
    }

    private function xmlValue(string $value): string
    {
        return trim($value);
    }

    private function formatXmlDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d\\TH:i:s');
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function normalizeVariantGr(?array $payload): string
    {
        $candidate = '';
        if ($payload !== null) {
            foreach (['Gr', 'gr', 'size', 'variant', 'option'] as $key) {
                $value = $payload[$key] ?? null;
                if (is_scalar($value) && trim((string) $value) !== '') {
                    $candidate = (string) $value;

                    break;
                }
            }
        }

        $normalized = strtoupper(trim($candidate));
        if ($normalized === '') {
            return '00';
        }

        return str_pad(mb_substr($normalized, 0, 2), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param array<int, string> $requiredNodes
     */
    private function assertXmlHasMandatoryNodes(\SimpleXMLElement $xml, array $requiredNodes): void
    {
        foreach ($requiredNodes as $nodeName) {
            if (!isset($xml->{$nodeName}) || trim((string) $xml->{$nodeName}) === '' && $xml->{$nodeName}->count() === 0) {
                throw new \RuntimeException(sprintf('Generated AuftragNeu2 XML is missing mandatory node "%s".', $nodeName));
            }
        }
    }

    private function maskSecrets(string $input): string
    {
        return (string) preg_replace('/(token|secret|authentifizierung|password)=([^&\s]+)/i', '$1=***', $input);
    }
}
