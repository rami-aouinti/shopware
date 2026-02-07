<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PhpStandardLibrary\Language;

function makeSentence(string $string): string
{
    $string = ucfirst($string);
    if (!in_array(mb_substr($string, -1), ['.', '!', '?'])) {
        $string .= '.';
    }

    return $string;
}
