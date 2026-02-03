<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ValidationBundle\Annotation;

use Attribute;
use Pickware\ValidationBundle\Subscriber\JsonValidationAnnotationSubscriber;

/**
 * @see JsonValidationAnnotationSubscriber
 */
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
class JsonValidation
{
    public function __construct(public readonly string $schemaFilePath) {}
}
