<?php declare(strict_types=1);

namespace ExternalOrders\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;

class ExternalToOrderMapper
{
    private const FALLBACK_CURRENCY_ID = 'b7d2554b0ce847cd82f3ac9bd1c0dfca';
    private const FALLBACK_SALES_CHANNEL_ID = '98432def39fc4624b33213a56b8c944d';
    private const FALLBACK_COUNTRY_ID = 'de4f1aaf5a284f8e8a5c8f65f68f4f41';
    private const FALLBACK_PAYMENT_METHOD_ID = '8ca10bca3ac84b87bb7f0d2f31f4f8a3';
    private const FALLBACK_SHIPPING_METHOD_ID = '0fa91ce3e96a4bc2be4bd9ce752c3425';

    /** @var array<string, string>|null */
    private ?array $resolvedFallbacks = null;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param array<string, mixed> $externalOrder
     *
     * @return array<string, mixed>
     */
    public function mapToOrderPayload(array $externalOrder, string $channel, string $externalId): array
    {
        $fallbacks = $this->resolveFallbacks();

        $currencyId = $this->pickHexId($externalOrder, ['currencyId']) ?? $fallbacks['currencyId'];
        $salesChannelId = $this->pickHexId($externalOrder, ['salesChannelId']) ?? $fallbacks['salesChannelId'];
        $countryId = $this->pickHexId($externalOrder, ['countryId'])
            ?? $this->pickHexId($externalOrder, ['billingAddress.countryId'])
            ?? $fallbacks['countryId'];
        $paymentMethodId = $this->pickHexId($externalOrder, ['paymentMethodId']) ?? $fallbacks['paymentMethodId'];
        $shippingMethodId = $this->pickHexId($externalOrder, ['shippingMethodId']) ?? $fallbacks['shippingMethodId'];

        $orderNumber = $this->pickString($externalOrder, ['orderNumber', 'id', 'externalId']) ?? $externalId;
        $addressId = Uuid::fromStringToHex('external-order-address-' . $externalId);
        $lineItemId = Uuid::fromStringToHex('external-order-line-item-' . $externalId);

        $quantity = max(1, $this->pickInt($externalOrder, ['quantity']) ?? 1);
        $unitPrice = $this->pickFloat($externalOrder, ['unitPrice', 'price', 'amount']) ?? 0.0;
        $totalPrice = $this->pickFloat($externalOrder, ['totalPrice', 'price', 'amount']) ?? (float) ($unitPrice * $quantity);

        $taxRules = [['taxRate' => 0.0, 'percentage' => 100.0]];
        $calculatedTaxes = [['tax' => 0.0, 'taxRate' => 0.0, 'price' => $totalPrice]];

        $price = [
            'netPrice' => $totalPrice,
            'totalPrice' => $totalPrice,
            'positionPrice' => $totalPrice,
            'taxStatus' => 'gross',
            'rawTotal' => $totalPrice,
            'calculatedTaxes' => $calculatedTaxes,
            'taxRules' => $taxRules,
        ];

        $billingAddress = [
            'id' => $addressId,
            'firstName' => $this->pickString($externalOrder, ['billingAddress.firstName', 'firstName']) ?? 'External',
            'lastName' => $this->pickString($externalOrder, ['billingAddress.lastName', 'lastName']) ?? 'Customer',
            'street' => $this->pickString($externalOrder, ['billingAddress.street', 'street']) ?? 'Fallback Street 1',
            'zipcode' => $this->pickString($externalOrder, ['billingAddress.zipcode', 'zipcode']) ?? '00000',
            'city' => $this->pickString($externalOrder, ['billingAddress.city', 'city']) ?? 'Unknown City',
            'countryId' => $countryId,
            'phoneNumber' => $this->pickString($externalOrder, ['billingAddress.phoneNumber', 'phoneNumber']) ?? null,
        ];

        return [
            'id' => Uuid::fromStringToHex('external-order-' . $externalId),
            'orderNumber' => $orderNumber,
            'currencyId' => $currencyId,
            'salesChannelId' => $salesChannelId,
            'currencyFactor' => 1.0,
            'orderDateTime' => $this->pickString($externalOrder, ['orderDateTime', 'orderDate']) ?? (new \DateTimeImmutable())->format(DATE_ATOM),
            'itemRounding' => ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true],
            'totalRounding' => ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true],
            'billingAddressId' => $addressId,
            'billingAddress' => $billingAddress,
            'addresses' => [$billingAddress],
            'lineItems' => [[
                'id' => $lineItemId,
                'identifier' => $externalId,
                'type' => 'custom',
                'referencedId' => null,
                'quantity' => $quantity,
                'label' => $this->pickString($externalOrder, ['lineItem.label', 'label']) ?? ('External item ' . $externalId),
                'priceDefinition' => [
                    'type' => 'quantity',
                    'price' => $unitPrice,
                    'quantity' => $quantity,
                    'isCalculated' => true,
                    'taxRules' => $taxRules,
                ],
                'price' => [
                    'unitPrice' => $unitPrice,
                    'totalPrice' => $totalPrice,
                    'quantity' => $quantity,
                    'calculatedTaxes' => $calculatedTaxes,
                    'taxRules' => $taxRules,
                ],
            ]],
            'deliveries' => [[
                'shippingMethodId' => $shippingMethodId,
                'shippingOrderAddressId' => $addressId,
                'shippingDateEarliest' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'shippingDateLatest' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'shippingCosts' => [
                    'unitPrice' => 0.0,
                    'totalPrice' => 0.0,
                    'quantity' => 1,
                    'calculatedTaxes' => [['tax' => 0.0, 'taxRate' => 0.0, 'price' => 0.0]],
                    'taxRules' => $taxRules,
                ],
                'positions' => [[
                    'orderLineItemId' => $lineItemId,
                    'price' => [
                        'unitPrice' => $unitPrice,
                        'totalPrice' => $totalPrice,
                        'quantity' => $quantity,
                        'calculatedTaxes' => $calculatedTaxes,
                        'taxRules' => $taxRules,
                    ],
                ]],
            ]],
            'transactions' => [[
                'paymentMethodId' => $paymentMethodId,
                'amount' => $price,
            ]],
            'price' => $price,
            'shippingCosts' => [
                'unitPrice' => 0.0,
                'totalPrice' => 0.0,
                'quantity' => 1,
                'calculatedTaxes' => [['tax' => 0.0, 'taxRate' => 0.0, 'price' => 0.0]],
                'taxRules' => $taxRules,
            ],
            'customFields' => [
                'external_order_id' => $externalId,
                'external_order_channel' => $channel,
            ],
        ];
    }

    /**
     * @return array{currencyId: string, salesChannelId: string, countryId: string, paymentMethodId: string, shippingMethodId: string}
     */
    private function resolveFallbacks(): array
    {
        if ($this->resolvedFallbacks !== null) {
            return $this->resolvedFallbacks;
        }

        $currencyId = $this->queryFirstHexId('SELECT LOWER(HEX(id)) FROM currency ORDER BY created_at ASC LIMIT 1') ?? self::FALLBACK_CURRENCY_ID;
        $salesChannelId = $this->queryFirstHexId('SELECT LOWER(HEX(id)) FROM sales_channel ORDER BY created_at ASC LIMIT 1') ?? self::FALLBACK_SALES_CHANNEL_ID;
        $countryId = $this->queryFirstHexId('SELECT LOWER(HEX(id)) FROM country ORDER BY created_at ASC LIMIT 1') ?? self::FALLBACK_COUNTRY_ID;
        $paymentMethodId = $this->queryFirstHexId('SELECT LOWER(HEX(id)) FROM payment_method ORDER BY created_at ASC LIMIT 1') ?? self::FALLBACK_PAYMENT_METHOD_ID;
        $shippingMethodId = $this->queryFirstHexId('SELECT LOWER(HEX(id)) FROM shipping_method ORDER BY created_at ASC LIMIT 1') ?? self::FALLBACK_SHIPPING_METHOD_ID;

        return $this->resolvedFallbacks = [
            'currencyId' => $currencyId,
            'salesChannelId' => $salesChannelId,
            'countryId' => $countryId,
            'paymentMethodId' => $paymentMethodId,
            'shippingMethodId' => $shippingMethodId,
        ];
    }

    private function queryFirstHexId(string $sql): ?string
    {
        $value = $this->connection->fetchOne($sql);

        if (!is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        return Uuid::isValid($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $paths
     */
    private function pickString(array $payload, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = $this->getByPath($payload, $path);
            if (!is_scalar($value)) {
                continue;
            }

            $normalized = trim((string) $value);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $paths
     */
    private function pickHexId(array $payload, array $paths): ?string
    {
        $candidate = $this->pickString($payload, $paths);
        if ($candidate === null) {
            return null;
        }

        return Uuid::isValid($candidate) ? strtolower($candidate) : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $paths
     */
    private function pickInt(array $payload, array $paths): ?int
    {
        $candidate = $this->pickString($payload, $paths);
        if ($candidate === null || !is_numeric($candidate)) {
            return null;
        }

        return (int) $candidate;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $paths
     */
    private function pickFloat(array $payload, array $paths): ?float
    {
        $candidate = $this->pickString($payload, $paths);
        if ($candidate === null) {
            return null;
        }

        $candidate = str_replace(',', '.', $candidate);
        if (!is_numeric($candidate)) {
            return null;
        }

        return (float) $candidate;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function getByPath(array $payload, string $path): mixed
    {
        $cursor = $payload;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }
}
