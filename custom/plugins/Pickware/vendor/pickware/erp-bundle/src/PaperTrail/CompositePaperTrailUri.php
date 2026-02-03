<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PaperTrail;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Should be used by process steps which aggregate multiple paper trail events.
 * Since the responsible bundle and process name are no longer precisely defined,
 * this uri replaces them with a placeholder.
 */
#[Exclude]
class CompositePaperTrailUri extends AbstractPaperTrailUri
{
    private const PLACEHOLDER = 'composite';

    protected function getResponsibleBundle(): string
    {
        return self::PLACEHOLDER;
    }

    protected function getProcessName(): string
    {
        return self::PLACEHOLDER;
    }
}
