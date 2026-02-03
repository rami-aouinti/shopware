<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\GenericEXTFCSVExport;

/**
 * data corresponds to https://developer.datev.de/de/file-format/details/datev-format/format-description/header
 */
enum EXTFCSVExportFormat
{
    case EntryBatch;
    case AccountLabel;
    case BaseData;

    public function getDisplayName(): string
    {
        return match ($this) {
            self::EntryBatch => 'Buchungsstapel',
            self::AccountLabel => 'Sachkontenbeschriftungen',
            self::BaseData => 'Debitorenstammdaten',
        };
    }

    public function getName(): string
    {
        return match ($this) {
            self::EntryBatch => 'Buchungsstapel',
            self::AccountLabel => 'Kontenbeschriftungen',
            self::BaseData => 'Debitoren/Kreditoren',
        };
    }

    public function getCategory(): int
    {
        return match ($this) {
            self::EntryBatch => 21,
            self::AccountLabel => 20,
            self::BaseData => 16,
        };
    }

    public function getVersion(): int
    {
        return match ($this) {
            self::EntryBatch => 12,
            self::AccountLabel => 3,
            self::BaseData => 5,
        };
    }
}
