<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Sync\Adapter;

class GambioOrderAdapter implements ChannelOrderAdapterInterface
{
    public function supports(string $channel, array $payload): bool
    {
        return strtolower($channel) === 'gambio' || strtolower((string) ($payload['sourceSystem'] ?? '')) === 'gambio';
    }

    public function normalize(array $payload): array
    {
        $payload['sourceSystem'] = 'gambio';
        $payload['customerEmail'] = $payload['customerEmail'] ?? ($payload['customer']['email'] ?? null);
        $payload['customerFirstName'] = $payload['customerFirstName']
            ?? ($payload['customer']['firstName'] ?? $payload['customer']['firstname'] ?? $payload['billingAddress']['firstName'] ?? null);
        $payload['customerLastName'] = $payload['customerLastName']
            ?? ($payload['customer']['lastName'] ?? $payload['customer']['lastname'] ?? $payload['billingAddress']['lastName'] ?? null);
        $payload['customerAdditionalName'] = $payload['customerAdditionalName']
            ?? ($payload['customer']['additionalName'] ?? $payload['customer']['company'] ?? $payload['billingAddress']['additionalAddressLine1'] ?? null);
        $payload['paymentMethod'] = $payload['paymentMethod'] ?? ($payload['payment']['title'] ?? null);
        $payload['paymentDate'] = $payload['paymentDate'] ?? ($payload['payment']['date'] ?? null);

        return $payload;
    }
}
