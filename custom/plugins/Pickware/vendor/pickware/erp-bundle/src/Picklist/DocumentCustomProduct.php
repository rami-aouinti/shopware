<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picklist;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class DocumentCustomProduct
{
    private string $type;
    private string $label;
    private ?string $value;
    private ?string $productNumber;

    public function __construct(string $type, string $label, ?string $value, ?string $productNumber = null)
    {
        $this->type = $type;
        $this->label = $label;
        $this->productNumber = $productNumber;
        $this->value = $value;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getProductNumber(): ?string
    {
        return $this->productNumber;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }
}
