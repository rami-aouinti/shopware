<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\DocumentBundle\Renderer\DocumentRendererException;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;
use Throwable;

class SupplierOrderException extends Exception implements JsonApiErrorSerializable
{
    public function __construct(private readonly JsonApiError $jsonApiError, ?Throwable $previous = null)
    {
        parent::__construct($jsonApiError->getDetail(), previous: $previous);
    }

    public static function documentRenderingFailed(DocumentRendererException $exception): self
    {
        $previousError = $exception->serializeToJsonApiError();

        return new self(new LocalizableJsonApiError([
            'title' => [
                'en' => 'Document rendering failed',
                'de' => 'Dokumenterstellung fehlgeschlagen',
            ],
            'detail' => [
                'en' => 'The document could not be rendered: ' . $previousError->getLocalizedDetail('en'),
                'de' => 'Das Dokument konnte nicht erstellt werden: ' . $previousError->getLocalizedDetail('de'),
            ],
            'meta' => [
                'previousError' => $previousError,
            ],
        ]), previous: $exception);
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }
}
