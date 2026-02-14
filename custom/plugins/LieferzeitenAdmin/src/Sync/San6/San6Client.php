<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Sync\San6;

use LieferzeitenAdmin\Service\Reliability\IntegrationReliabilityService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class San6Client
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SystemConfigService $config,
        private readonly IntegrationReliabilityService $reliabilityService,
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

        return $this->reliabilityService->executeWithRetry('san6', 'fetchByOrderNumber', function () use ($url, $options): array {
            $response = $this->httpClient->request('GET', $url, $options);
            $data = $response->toArray(false);

            return is_array($data) ? $this->normalizeResponse($data) : [];
        }, payload: ['query' => ['orderNumber' => $orderNumber]]);
    }

    /** @param array<string,mixed> $data */
    private function normalizeResponse(array $data): array
    {
        $payload = $this->extractOrderPayload($data);
        if ($payload === []) {
            return [];
        }

        $parcels = $this->extractParcels($payload);

        return [
            'orderNumber' => $payload['orderNumber'] ?? $payload['order_number'] ?? $payload['orderNo'] ?? null,
            'shippingDate' => $payload['shippingDate'] ?? $payload['shipping_date'] ?? null,
            'deliveryDate' => $payload['deliveryDate'] ?? $payload['delivery_date'] ?? null,
            'status' => $payload['status'] ?? $payload['state'] ?? null,
            'sourceSystem' => $payload['sourceSystem'] ?? $payload['source'] ?? 'san6',
            'customer' => is_array($payload['customer'] ?? null) ? $payload['customer'] : null,
            'payment' => is_array($payload['payment'] ?? null) ? $payload['payment'] : null,
            'parcels' => $parcels,
        ];
    }

    /** @param array<string,mixed> $data
     *  @return array<string,mixed>
     */
    private function extractOrderPayload(array $data): array
    {
        $candidates = [$data['data'] ?? null, $data['result'] ?? null, $data['order'] ?? null, $data['bestellung'] ?? null, $data];
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            if (is_array($candidate['order'] ?? null)) {
                return $candidate['order'];
            }

            return $candidate;
        }

        return [];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<int,array<string,mixed>>
     */
    private function extractParcels(array $payload): array
    {
        $rawParcels = $payload['parcels'] ?? $payload['packages'] ?? $payload['shipments'] ?? $payload['pakete'] ?? [];
        if (!is_array($rawParcels)) {
            return [];
        }

        $parcels = [];
        foreach ($rawParcels as $parcel) {
            if (!is_array($parcel)) {
                continue;
            }

            $parcels[] = [
                'paketNumber' => $parcel['paketNumber'] ?? $parcel['packageNumber'] ?? $parcel['parcelNumber'] ?? $parcel['number'] ?? null,
                'trackingNumber' => $parcel['trackingNumber'] ?? $parcel['sendenummer'] ?? $parcel['tracking_no'] ?? null,
                'status' => $parcel['status'] ?? $parcel['state'] ?? $parcel['trackingStatus'] ?? null,
                'shippingDate' => $parcel['shippingDate'] ?? $parcel['shipping_date'] ?? null,
                'deliveryDate' => $parcel['deliveryDate'] ?? $parcel['delivery_date'] ?? null,
                'carrier' => $parcel['carrier'] ?? null,
            ] + $parcel;
        }

        return $parcels;
    }
}
