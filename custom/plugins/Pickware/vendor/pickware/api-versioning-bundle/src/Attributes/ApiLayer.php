<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ApiVersioningBundle\Attributes;

use Attribute;

/**
 * A controller action annotation for referencing instances of `ApiLayer` that should be applied to the annotated
 * action.
 */
#[Attribute(flags: Attribute::TARGET_METHOD)]
class ApiLayer
{
    /**
     * @param string[] $ids The service IDs of the API layers that should be applied.
     */
    public function __construct(public readonly array $ids) {}
}
