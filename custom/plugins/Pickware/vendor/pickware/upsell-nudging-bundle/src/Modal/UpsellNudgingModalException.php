<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UpsellNudgingBundle\Modal;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class UpsellNudgingModalException extends Exception implements JsonApiErrorSerializable
{
    public function __construct(private readonly JsonApiError $jsonApiError)
    {
        parent::__construct($this->jsonApiError->getDetail());
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public static function pickwareAccountNotConnected(): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'en' => 'Pickware Account not connected.',
                'de' => 'Pickware Account nicht verbunden.',
            ],
            'detail' => [
                'en' => 'A Pickware Account must be connected to use upsell nudging.',
                'de' => 'Ein Pickware Account muss verbunden sein, um upsell nudging zu nutzen.',
            ],
        ]));
    }
}
