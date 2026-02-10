<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Sync\Adapter;

class ShopwareOrderAdapter implements ChannelOrderAdapterInterface
{
    public function supports(string $channel, array $payload): bool
    {
        return strtolower($channel) === 'shopware' || strtolower((string) ($payload['sourceSystem'] ?? '')) === 'shopware';
    }

    public function normalize(array $payload): array
    {
        $payload['sourceSystem'] = 'shopware';
        $payload['customerEmail'] = $payload['customerEmail'] ?? ($payload['orderCustomer']['email'] ?? null);
        $payload['paymentMethod'] = $payload['paymentMethod'] ?? ($payload['transactions'][0]['paymentMethod']['name'] ?? null);
        $payload['paymentDate'] = $payload['paymentDate']
            ?? ($payload['transactions'][0]['createdAt'] ?? null);

        return $payload;
    }
}
