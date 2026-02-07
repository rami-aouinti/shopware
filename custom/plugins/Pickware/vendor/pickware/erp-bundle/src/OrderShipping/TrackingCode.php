<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderShipping;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class TrackingCode
{
    private string $code;
    private ?string $url;

    public function __construct(string $code, ?string $url)
    {
        $this->code = $code;
        $this->url = $url;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }
}
