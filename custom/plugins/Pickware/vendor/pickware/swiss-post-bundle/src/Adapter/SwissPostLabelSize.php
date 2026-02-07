<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SwissPostBundle\Adapter;

use Pickware\DocumentBundle\Document\PageFormat;

enum SwissPostLabelSize: string
{
    case A6 = 'A6';

    public function getPageFormat(): PageFormat
    {
        return match ($this) {
            self::A6 => new PageFormat(
                'Swiss Post Label A6',
                PageFormat::createDinPageFormat('A6')->getSize(),
                'swiss_post_a6',
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
