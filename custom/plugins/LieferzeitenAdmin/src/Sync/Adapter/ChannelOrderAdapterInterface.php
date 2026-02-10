<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Sync\Adapter;

interface ChannelOrderAdapterInterface
{
    /** @param array<string,mixed> $payload */
    public function supports(string $channel, array $payload): bool;

    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    public function normalize(array $payload): array;
}
