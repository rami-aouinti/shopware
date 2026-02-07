<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DsvBundle\Adapter;

use Pickware\DocumentBundle\Document\PageFormat;

enum DsvLabelSize
{
    case A4;

    public function getPageFormat(): PageFormat
    {
        return match ($this) {
            self::A4 => new PageFormat(
                'DSV Label A4',
                PageFormat::createDinPageFormat('A4')->getSize(),
                'dsv_a4',
            ),
        };
    }

    public static function getSupportedPageFormats(): array
    {
        $pageFormats = [];

        foreach (self::cases() as $case) {
            $pageFormats[] = $case->getPageFormat();
        }

        return $pageFormats;
    }
}
