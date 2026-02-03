<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle\EntityDeletionProtection;

use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class WriteConstraintViolationExceptionFactory
{
    public static function create(string $message): WriteConstraintViolationException
    {
        return new WriteConstraintViolationException(new ConstraintViolationList([
            new ConstraintViolation(
                message: $message,
                messageTemplate: $message,
                parameters: [],
                root: null,
                propertyPath: null,
                invalidValue: null,
            ),
        ]));
    }
}
