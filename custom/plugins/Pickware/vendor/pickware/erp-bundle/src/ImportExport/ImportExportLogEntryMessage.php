<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport;

use JsonSerializable;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;

readonly class ImportExportLogEntryMessage implements JsonSerializable
{
    /**
     * NOTE: This constructor was previously private, but changed to public as part of https://github.com/pickware/shopware-plugins/pull/8059
     * to allow for easier instantiation of this class in the future. The change was released as part of a minor version bump.
     *
     * @param array{'de-DE': string, 'en-GB': string} $content
     * @param string|null $errorCode An error code, usually given in the old format of `JsonApiError` where translations
     */
    public function __construct(
        private array $content,
        private array $meta,
        private ?string $errorCode = null,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'content' => $this->content,
            'meta' => $this->meta,
            'errorCode' => $this->errorCode,
        ];
    }

    public static function fromArray(array $array): self
    {
        return new self(
            $array['content'],
            $array['meta'] ?? [],
            $array['errorCode'] ?? null,
        );
    }

    public static function fromJsonApiError(JsonApiError $jsonApiError): self
    {
        $meta = $jsonApiError->getMeta() ?? [];
        unset($meta[LocalizableJsonApiError::META_PROPERTY_NAME]);

        $localizableJsonApiError = LocalizableJsonApiError::createFromJsonApiError($jsonApiError);
        $localizedJsonApiErrors = $localizableJsonApiError->createAllLocalizedErrors();

        $content = [];
        foreach ($localizedJsonApiErrors as $locale => $localizedJsonApiError) {
            $content[$locale] = $localizedJsonApiError->getDetail();
        }

        return new self(
            $content,
            $meta,
            $jsonApiError->getCode(),
        );
    }

    public static function fromTranslatedMessage(TranslatedMessage $translatedMessage): self
    {
        return new self(
            $translatedMessage->getContent(),
            $translatedMessage->getMeta(),
        );
    }
}
