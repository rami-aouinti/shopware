<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary\NumberRange;

class NumberRange
{
    private string $technicalName;
    private string $pattern;
    private int $start;
    private array $translations;

    /**
     * @param string $technicalName Matches number_range_type.technical_name. Use the name of the entity if this number
     * range belongs to an entity
     * @param string $pattern Matches number_range.pattern (e.g. '{n}')
     * @param int $start Matches number_range.start (e.g. 1000)
     * @param array $translations Number range and number range type name for the locale codes de-DE and en-GB. E.g. [
     *   'de-DE' => 'Lieferanten',
     *   'en-GB' => 'Suppliers',
     * ]
     */
    public function __construct(string $technicalName, string $pattern, int $start, array $translations)
    {
        $this->technicalName = $technicalName;
        $this->pattern = $pattern;
        $this->start = $start;
        $this->translations = $translations;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getStart(): int
    {
        return $this->start;
    }

    public function getTranslations(): array
    {
        return $this->translations;
    }

    public function getTypeNameTranslations(): array
    {
        $translations = [];
        foreach ($this->translations as $localeCode => $translation) {
            $translations[$localeCode] = $translation;
        }

        return $translations;
    }
}
