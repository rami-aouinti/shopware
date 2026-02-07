<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\CustomField;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class MediaFormatterError extends Exception implements JsonApiErrorSerializable
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

    public static function mediaTypeNotSupported(string $mediaType): self
    {
        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'en' => 'The media type is not supported',
                    'de' => 'Der Medientyp wird nicht unterstützt',
                ],
                'detail' => [
                    'en' => sprintf('The media type "%s" is not supported for formatting.', $mediaType),
                    'de' => sprintf('Der Medientyp "%s" kann nicht formatiert werden', $mediaType),
                ],
            ]),
        );
    }

    public static function fileSizeTooBig(int $sizeInBytes, int $maxSizeInBytes): self
    {
        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'en' => 'The media cannot be formatted because the file size is too big.',
                    'de' => 'Die Mediendatei kann nicht formatiert werden, da die Dateigröße zu groß ist.',
                ],
                'detail' => [
                    'en' => sprintf(
                        'The media file size is %d bytes, but the maximum allowed size for formatting is %d bytes.',
                        $sizeInBytes,
                        $maxSizeInBytes,
                    ),
                    'de' => sprintf(
                        'Die Dateigröße der Mediendatei beträgt %d Bytes, die maximal erlaubte Größe für die Formatierung ist jedoch %d Bytes.',
                        $sizeInBytes,
                        $maxSizeInBytes,
                    ),
                ],
            ]),
        );
    }
}
