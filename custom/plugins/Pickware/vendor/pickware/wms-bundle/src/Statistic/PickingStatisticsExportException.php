<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Statistic;

use Exception;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class PickingStatisticsExportException extends Exception implements JsonApiErrorSerializable
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_WMS__PICKING_STATISTICS_EXPORT__';
    private const ERROR_INVALID_TIMEZONE = self::ERROR_CODE_NAMESPACE . 'INVALID_TIMEZONE';

    private function __construct(private readonly JsonApiError $jsonApiError)
    {
        parent::__construct($this->jsonApiError->getDetail());
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public static function createInvalidTimezoneError(string $invalidTimezone): self
    {
        return new self(new JsonApiError([
            'code' => self::ERROR_INVALID_TIMEZONE,
            'title' => 'Invalid timezone',
            'detail' => sprintf('"%s" is not a valid timezone identifier.', $invalidTimezone),
            'meta' => ['invalidTimezone' => $invalidTimezone],
        ]));
    }
}
