<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DpdBundle\Api;

use Pickware\DpdBundle\Adapter\DpdAdapterException;

enum DpdProduct: string
{
    case DpdClassic = 'CL';
    case Dpd830 = 'E830';
    case Dpd1000 = 'E10';
    case Dpd1200 = 'E12';
    case Dpd1800 = 'E18';
    case DpdInternationalMail = 'MAIL';
    case DpdMax = 'MAX';
    case DpdParcelletter = 'PL';
    case DpdPriority = 'PM4';

    public static function getByCode(string $code): self
    {
        $product = self::tryFrom($code);

        if (!$product) {
            throw DpdAdapterException::invalidProductCode($code);
        }

        return $product;
    }

    public function getCode(): string
    {
        return $this->value;
    }
}
