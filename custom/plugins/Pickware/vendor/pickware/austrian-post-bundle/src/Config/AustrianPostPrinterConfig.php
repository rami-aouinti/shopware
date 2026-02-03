<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\AustrianPostBundle\Config;

use Pickware\AustrianPostBundle\Adapter\AustrianPostLabelSize;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class AustrianPostPrinterConfig
{
    public function __construct(
        private readonly AustrianPostLabelSize $labelSize,
    ) {}

    public function getPrinterObject(): array
    {
        return [
            'LabelFormatID' => $this->labelSize->getLabelFormatId(),
            'LanguageID' => 'PDF',
            'PaperLayoutID' => $this->labelSize->getPaperLayoutId(),
        ];
    }
}
