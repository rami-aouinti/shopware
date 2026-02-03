<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentBundle\Renderer;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class DocumentRendererException extends Exception implements JsonApiErrorSerializable
{
    public function __construct(private LocalizableJsonApiError $jsonApiError)
    {
        parent::__construct($jsonApiError->getDetail());
    }

    public function serializeToJsonApiError(): LocalizableJsonApiError
    {
        return $this->jsonApiError;
    }

    public static function snippetSetNotFound(string $localeCode): self
    {
        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'en' => 'Snippet set not found',
                    'de' => 'Textbaustein-Set nicht gefunden',
                ],
                'detail' => [
                    'en' => sprintf(
                        'The snippet set for the locale "%s" could not be found. Create a snippet set for the locale in the settings.',
                        $localeCode,
                    ),
                    'de' => sprintf(
                        'Das Textbaustein-Set für die Locale "%s" konnte nicht gefunden werden. Lege in den Einstellungen ein Textbaustein-Satz für die Locale an.',
                        $localeCode,
                    ),
                ],
                'meta' => [
                    'localeCode' => $localeCode,
                ],
            ]),
        );
    }
}
