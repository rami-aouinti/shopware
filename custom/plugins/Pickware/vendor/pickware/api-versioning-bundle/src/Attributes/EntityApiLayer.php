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
 * An annotation for classes implementing `ApiLayer` that should be applied to an admin API route (e.g. "search") of a
 * specific entity.
 */
#[Attribute(flags: Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class EntityApiLayer
{
    /**
     * @param string $entity The name of the entity whose API route should be transformed.
     * @param string $method The method of the API route that should be transformed. The list of allowed methods is
     * based on the actions provided by `Shopware\Core\Framework\Api\Controller\ApiController`, which are
     * resolved to the respective API routes by `Shopware\Core\Framework\Api\Route\ApiRouteLoader`.
     */
    public function __construct(public readonly string $entity, public readonly string $method) {}
}
