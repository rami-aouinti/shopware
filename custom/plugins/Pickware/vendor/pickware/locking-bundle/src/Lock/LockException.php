<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LockingBundle\Lock;

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\ApiErrorHandlingBundle\ServerOverloadException\ServerOverloadException;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;

class LockException extends ServerOverloadException
{
    public function __construct(JsonApiError $jsonApiError)
    {
        parent::__construct(new JsonApiErrors([$jsonApiError]));
    }

    public static function maxWaitTimeReached(float $maxWaitTime, LockIdProvider $lockIdProvider): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'de' => 'Die maximale Wartezeit für das Sperren der Ressource wurde erreicht.',
                'en' => 'The maximum wait time for locking the resource has been reached.',
            ],
            'detail' => [
                'de' => sprintf('Die maximale Wartezeit von %s Sekunden für das Sperren der Ressource "%s" wurde erreicht.', $maxWaitTime, $lockIdProvider->getLockId()),
                'en' => sprintf('The maximum wait time of %s seconds for locking the resource "%s" has been reached.', $maxWaitTime, $lockIdProvider->getLockId()),
            ],
            'meta' => [
                'maxWaitTimeInSeconds' => $maxWaitTime,
                'lockId' => $lockIdProvider->getLockId(),
            ],
        ]));
    }

    public static function maxRetriesReached(int $maxRetries, LockIdProvider $lockIdProvider): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'de' => 'Die maximale Anzahl an Versuchen für das Sperren der Ressource wurde erreicht.',
                'en' => 'The maximum number of retries for locking the resource has been reached.',
            ],
            'detail' => [
                'de' => sprintf('Die maximale Anzahl an Versuchen von %s für das Sperren der Ressource "%s" wurde erreicht.', $maxRetries, $lockIdProvider->getLockId()),
                'en' => sprintf('The maximum number of retries of %s for locking the resource "%s" has been reached.', $maxRetries, $lockIdProvider->getLockId()),
            ],
            'meta' => [
                'maxRetries' => $maxRetries,
                'lockId' => $lockIdProvider->getLockId(),
            ],
        ]));
    }
}
