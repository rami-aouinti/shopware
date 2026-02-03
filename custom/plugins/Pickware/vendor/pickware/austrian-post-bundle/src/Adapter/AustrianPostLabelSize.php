<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\AustrianPostBundle\Adapter;

use Pickware\DocumentBundle\Document\PageFormat;

enum AustrianPostLabelSize: string
{
    case A5 = 'A5';
    case A6 = 'A6';

    public function getPageFormat(): PageFormat
    {
        return match ($this) {
            self::A5 => new PageFormat(
                description: 'Austrian Post Label A5',
                size: PageFormat::createDinPageFormat('A5')->getSize(),
                id: 'austrian_post_a5',
            ),
            self::A6 => new PageFormat(
                description: 'Austrian Post Label A6',
                size: PageFormat::createDinPageFormat('A6')->getSize(),
                id: 'austrian_post_a6',
            ),
        };
    }

    public function getLabelFormatId(): string
    {
        return match ($this) {
            self::A5 => '100x200',
            self::A6 => '100x150',
        };
    }

    public function getPaperLayoutId(): string
    {
        return $this->value;
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
