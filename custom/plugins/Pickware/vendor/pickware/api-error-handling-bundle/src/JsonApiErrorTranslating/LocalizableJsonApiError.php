<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating;

use InvalidArgumentException;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorLinks;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSource;

class LocalizableJsonApiError extends JsonApiError
{
    public const META_PROPERTY_NAME = '_localizedProperties';
    private const DEFAULT_LOCALE = 'en';

    // All our translatable error should contain a German and English translation
    private const REQUIRED_TRANSLATION_LOCALES = [
        self::DEFAULT_LOCALE,
        'de',
    ];

    /**
     * @param array{
     *      id?: mixed,
     *      links?: array|JsonApiErrorLinks|null,
     *      status?: string|int|null,
     *      code?: string|null,
     *      title?: array<string, string>|string|null,
     *      detail?: array<string, string>|string|null,
     *      source?: JsonApiErrorSource|null,
     *      meta?: array<string, mixed>|null,
     * } $properties
     */
    public function __construct(array $properties)
    {
        $localizedProperties = [];

        $title = $properties['title'] ?? null;
        if (is_array($title)) {
            self::validateRequiredTranslationsExist($properties['title']);
            $properties['title'] = $title[self::DEFAULT_LOCALE];
            $localizedProperties['title'] = $title;
        } elseif ($title !== null) {
            $localizedProperties['title'] = [self::DEFAULT_LOCALE => $title];
        }
        $detail = $properties['detail'] ?? null;
        if (is_array($detail)) {
            self::validateRequiredTranslationsExist($properties['detail']);
            $properties['detail'] = $detail[self::DEFAULT_LOCALE];
            $localizedProperties['detail'] = $detail;
        } elseif ($detail !== null) {
            $localizedProperties['detail'] = [self::DEFAULT_LOCALE => $detail];
        }
        $links = $properties['links'] ?? null;
        if (is_array($links) && !array_key_exists('about', $links) && !array_key_exists('type', $links)) {
            self::validateRequiredTranslationsExist($properties['links']);
            $properties['links'] = $links[self::DEFAULT_LOCALE];
            $localizedProperties['links'] = $links;
        }
        if (count($localizedProperties) !== 0) {
            $meta = $properties['meta'] ?? [];
            $meta[self::META_PROPERTY_NAME] = [
                ...$localizedProperties,
                ...$meta[self::META_PROPERTY_NAME] ?? [],
            ];
            $properties['meta'] = $meta;
        }

        parent::__construct($properties);
    }

    public static function createFromJsonApiError(JsonApiError $jsonApiError): self
    {
        return new self($jsonApiError->jsonSerialize());
    }

    /**
     * Returns a localized copy of itself where the title, detail and links properties are translated with the first
     * matching locale.
     */
    public function localize(array $locales): JsonApiError
    {
        $meta = $this->getMeta();
        $localizedProperties = $meta[self::META_PROPERTY_NAME] ?? null;
        $newProperties = [];
        if ($meta && $localizedProperties) {
            $newProperties['title'] = self::getPreferredLocalization($localizedProperties['title'] ?? null, $locales) ?? $this->getTitle();
            $newProperties['detail'] = self::getPreferredLocalization($localizedProperties['detail'] ?? null, $locales) ?? $this->getDetail();
            $newProperties['links'] = self::getPreferredLocalization($localizedProperties['links'] ?? null, $locales) ?? $this->getLinks();

            unset($meta[self::META_PROPERTY_NAME]);
            if (count($meta) === 0) {
                $meta = null;
            }
            $newProperties['meta'] = $meta;
        }

        return new JsonApiError([
            ...$this->jsonSerialize(),
            ...$newProperties,
        ]);
    }

    /**
     * @return JsonApiError[]
     */
    public function createAllLocalizedErrors(): array
    {
        $locales = array_unique([
            ...array_keys($this->getMeta()[self::META_PROPERTY_NAME]['title'] ?? []),
            ...array_keys($this->getMeta()[self::META_PROPERTY_NAME]['detail'] ?? []),
        ]);

        $keys = array_map(
            fn(string $locale) => match ($locale) {
                'de' => 'de-DE',
                'en' => 'en-GB',
                default => $locale,
            },
            $locales,
        );
        $values = array_map(
            fn(string $locale) => $this->localize([$locale]),
            $locales,
        );

        return array_combine($keys, $values);
    }

    public function setLocalizedLinks(array $localizedLinks): void
    {
        self::validateRequiredTranslationsExist($localizedLinks);
        $meta = $this->getMeta();
        $meta[self::META_PROPERTY_NAME]['links'] = $localizedLinks;
        $this->setMeta($meta);
        $this->setLinks($localizedLinks[self::DEFAULT_LOCALE]);
    }

    public function getLocalizedDetail(string $locale): ?string
    {
        $meta = $this->getMeta();
        $localizedProperties = $meta[self::META_PROPERTY_NAME] ?? null;
        $detail = $localizedProperties['detail'] ?? null;

        return $detail[$locale] ?? $this->getDetail();
    }

    private static function getPreferredLocalization(?array $translations, array $locales): mixed
    {
        $locale = array_shift($locales);
        if ($locale === null || $translations === null) {
            return null;
        }

        // Expected string format: `de-CH`. We only match the prefix `de` because we do not distinguish between regions.
        $locale = mb_substr($locale, 0, 2);

        $translation = $translations[$locale] ?? null;

        // Iterating over the preferred languages of the client to get the most preferred translation.
        return $translation ?? self::getPreferredLocalization($translations, $locales);
    }

    private static function validateRequiredTranslationsExist(array $translations): void
    {
        $existingLocales = array_keys($translations);
        if (count(array_diff(self::REQUIRED_TRANSLATION_LOCALES, $existingLocales)) !== 0) {
            throw new InvalidArgumentException(sprintf(
                'You have to provide translations for at least the following locales: %s.',
                implode(', ', self::REQUIRED_TRANSLATION_LOCALES),
            ));
        }
    }
}
