<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Flow;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class FlowWriteException extends Exception implements JsonApiErrorSerializable
{
    private JsonApiError $jsonApiError;

    public function __construct(JsonApiError $jsonApiError)
    {
        $this->jsonApiError = $jsonApiError;
        parent::__construct($jsonApiError->getDetail());
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public static function nonForceStatusUpdateInFlowActionNotAllowed($flowSequenceId): self
    {
        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'en' => 'The flow action cannot be written',
                    'de' => 'Die Flow Aktion kann nicht gespeichert werden',
                ],
                'detail' => [
                    'en' => 'The flow action cannot be written, because the "Assign status" flow action is not set to "Force status transitions".',
                    'de' => 'Die Flow Aktion kann nicht gespeichert werden, weil die "Status zuweisen" Flow Aktion nicht auf "Statuswechsel erzwingen" gesetzt ist.',
                ],
                'meta' => ['flowSequenceId' => $flowSequenceId],
            ]),
        );
    }
}
