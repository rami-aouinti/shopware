<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

class WriteEndpointConflictException extends \RuntimeException
{
    /** @param array<string, mixed> $refresh */
    public function __construct(
        private readonly array $refresh,
        ?string $message = null,
    ) {
        parent::__construct($message ?? 'The position was modified by another user. Refresh the row and retry.');
    }

    /** @return array<string, mixed> */
    public function getRefresh(): array
    {
        return $this->refresh;
    }
}

