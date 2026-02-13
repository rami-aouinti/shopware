<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Sync\Adapter;

class ChannelOrderAdapterRegistry
{
    /** @param iterable<ChannelOrderAdapterInterface> $adapters */
    public function __construct(private readonly iterable $adapters)
    {
    }

    /** @param array<string,mixed> $payload */
    public function resolve(string $channel, array $payload): ?ChannelOrderAdapterInterface
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($channel, $payload)) {
                return $adapter;
            }
        }

        return null;
    }
}
