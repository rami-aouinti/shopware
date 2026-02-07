<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Template;

use Exception;

class TwigException extends Exception
{
    public static function filterContextIsMissing(string $filterName): self
    {
        return new self(sprintf(
            'Error while processing Twig filter "%s". No context given.',
            $filterName,
        ));
    }

    public static function filterProcessingError(string $filterName, $errorMessage): self
    {
        return new self(sprintf(
            'Error while processing Twig filter "%s". Error message: %s',
            $filterName,
            $errorMessage,
        ));
    }
}
