<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle;

use InvalidArgumentException;
use JsonSerializable;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class Translation implements JsonSerializable
{
    private const LOCALE_DE_DE = 'de-DE';
    private const LOCALE_EN_GB = 'en-GB';
    public const REQUIRED_LOCALES = [
        self::LOCALE_DE_DE,
        self::LOCALE_EN_GB,
    ];

    public function __construct(
        private readonly string $german,
        private readonly string $english,
    ) {}

    public static function fromArray(array $translation): self
    {
        return new self(
            $translation[self::LOCALE_DE_DE] ?? $translation['de'],
            $translation[self::LOCALE_EN_GB] ?? $translation['en'],
        );
    }

    public function getGerman(): string
    {
        return $this->german;
    }

    public function getEnglish(): string
    {
        return $this->english;
    }

    public function getTranslation(string $locale): string
    {
        return match ($locale) {
            self::LOCALE_DE_DE => $this->german,
            self::LOCALE_EN_GB => $this->english,
            default => throw new InvalidArgumentException(sprintf('Unsupported locale: %s', $locale)),
        };
    }

    public function jsonSerialize(): array
    {
        return [
            self::LOCALE_DE_DE => $this->german,
            self::LOCALE_EN_GB => $this->english,
            // Also serialize to the deprecated language keys for backwards compatibility
            'de' => $this->german,
            'en' => $this->english,
        ];
    }
}
