<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Translation;

use Exception;

class TranslationException extends Exception
{
    public static function noSnippetSetFoundForLocale(string $localeCode): self
    {
        return new self(sprintf('No snippet set found for locale with code "%s"', $localeCode));
    }
}
