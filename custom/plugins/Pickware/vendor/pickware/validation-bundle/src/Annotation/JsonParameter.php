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
use Pickware\ValidationBundle\Subscriber\JsonRequestValueResolver;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Validator\Constraint;

/**
 * @see JsonRequestValueResolver
 */
#[Attribute(flags: Attribute::TARGET_PARAMETER)]
class JsonParameter extends ValueResolver
{
    private ArgumentMetadata $argumentMetadata;

    public function __construct(
        /** @var Constraint[] */
        public readonly array $validations = [],
        string $resolver = JsonRequestValueResolver::class,
    ) {
        parent::__construct($resolver);
    }

    public function getArgumentMetadata(): ArgumentMetadata
    {
        return $this->argumentMetadata;
    }

    public function setArgumentMetadata(ArgumentMetadata $argumentMetadata): void
    {
        $this->argumentMetadata = $argumentMetadata;
    }
}
