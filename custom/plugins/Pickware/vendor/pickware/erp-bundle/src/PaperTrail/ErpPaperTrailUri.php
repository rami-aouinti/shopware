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

#[Exclude]
class ErpPaperTrailUri extends AbstractPaperTrailUri
{
    private const RESPONSIBLE_BUNDLE = 'erp';

    private function __construct(
        private readonly string $processName,
    ) {
        parent::__construct();
    }

    public static function withProcess(string $processName): self
    {
        return new self($processName);
    }

    protected function getResponsibleBundle(): string
    {
        return self::RESPONSIBLE_BUNDLE;
    }

    protected function getProcessName(): string
    {
        return $this->processName;
    }
}
