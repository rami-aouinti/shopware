<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DhlExpressBundle\Adapter;

use Pickware\DocumentBundle\Document\PageFormat;

enum DhlExpressLabelSize
{
    case A5;

    public function getPageFormat(): PageFormat
    {
        return match ($this) {
            self::A5 => new PageFormat(
                'DHL Express Label A5',
                PageFormat::createDinPageFormat('A5')->getSize(),
                'dhl_express_a5',
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
