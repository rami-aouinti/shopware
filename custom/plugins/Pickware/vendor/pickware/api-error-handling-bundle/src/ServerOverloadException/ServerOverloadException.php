<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ApiErrorHandlingBundle\ServerOverloadException;

use DateTimeImmutable;
use Exception;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\HttpUtils\JsonApi\JsonApiErrorsSerializable;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;
use Throwable;

#[WithLogLevel(LogLevel::INFO)]
#[WithHttpStatus(Response::HTTP_SERVICE_UNAVAILABLE)]
class ServerOverloadException extends Exception implements JsonApiErrorsSerializable
{
    private ?DateTimeImmutable $retryAfter = null;

    public function __construct(
        private readonly JsonApiErrors $jsonApiErrors,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: $this->jsonApiErrors->getThrowableMessage(),
            previous: $previous,
        );
    }

    public function serializeToJsonApiErrors(): JsonApiErrors
    {
        return $this->jsonApiErrors;
    }

    public function setRetryAfter(DateTimeImmutable $retryAfter): void
    {
        $this->retryAfter = $retryAfter;
    }

    public function getRetryAfter(): ?DateTimeImmutable
    {
        return $this->retryAfter;
    }
}
