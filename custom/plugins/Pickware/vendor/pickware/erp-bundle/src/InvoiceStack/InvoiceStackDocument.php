<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\InvoiceStack;

use DateTimeInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class InvoiceStackDocument
{
    public string $id;
    public string $number;
    public DateTimeInterface $createdAt;

    public function __construct(string $id, string $number, DateTimeInterface $createdAt)
    {
        $this->id = $id;
        $this->number = $number;
        $this->createdAt = $createdAt;
    }
}
