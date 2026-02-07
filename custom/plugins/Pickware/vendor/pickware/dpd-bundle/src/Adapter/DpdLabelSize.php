<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DpdBundle\Adapter;

use Pickware\DocumentBundle\Document\PageFormat;

enum DpdLabelSize: string
{
    case A4 = 'A4';
    case A6 = 'A6';

    public function getPageFormat(): PageFormat
    {
        return match ($this) {
            self::A4 => new PageFormat(
                'DPD Label A4',
                PageFormat::createDinPageFormat('A4')->getSize(),
                'dpd_a4',
            ),
            self::A6 => new PageFormat(
                'DPD Label A6',
                PageFormat::createDinPageFormat('A6')->getSize(),
                'dpd_a6',
            ),
        };
    }

    /**
     * @return PageFormat[]
     */
    public static function getSupportedPageFormats(): array
    {
        $pageFormats = [];

        foreach (self::cases() as $case) {
            $pageFormats[] = $case->getPageFormat();
        }

        return $pageFormats;
    }
}
