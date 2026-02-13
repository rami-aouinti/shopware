<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service\Tracking;

use RuntimeException;

class TrackingProviderException extends RuntimeException
{
    public function __construct(private readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
