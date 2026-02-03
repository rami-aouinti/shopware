<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UsageReportBundle\ApiClient;

use GuzzleHttp\Exception\TransferException;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Throwable;

class UsageReportApiNotReachableError extends UsageReportApiClientException
{
    private function __construct(
        private readonly LocalizableJsonApiError $jsonApiError,
        ?Throwable $previous = null,
    ) {
        parent::__construct($this->jsonApiError, $previous);
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public static function pickwareAccountNotReachable(TransferException $reason): self
    {
        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'de' => 'Pickware Account nicht erreichbar',
                    'en' => 'Pickware Account not reachable',
                ],
                'detail' => [
                    'en' => 'The Pickware Account is not reachable. Please contact Pickware support.',
                    'de' => 'Der Pickware Account ist nicht erreichbar. Bitte kontaktiere den Pickware Support.',
                ],
                'meta' => [
                    'exception' => $reason,
                ],
            ]),
            $reason,
        );
    }
}
