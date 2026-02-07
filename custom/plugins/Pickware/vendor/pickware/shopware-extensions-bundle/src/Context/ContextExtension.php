<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\Context;

use RuntimeException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\PlatformRequest;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpFoundation\Request;

#[Exclude]
class ContextExtension
{
    public static function getUserId(Context $context): string
    {
        $userId = self::findUserId($context);
        if ($userId === null) {
            throw new RuntimeException('The current context does not have a user.');
        }

        return $userId;
    }

    public static function hasUser(Context $context): bool
    {
        $userId = self::findUserId($context);

        return $userId !== null;
    }

    public static function findUserId(Context $context): ?string
    {
        $contextSource = $context->getSource();
        if ($contextSource instanceof AdminApiSource) {
            $userId = $contextSource->getUserId();
        } else {
            $userId = null;
        }

        return $userId;
    }

    public static function findFromRequest(Request $request): ?Context
    {
        return $request->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
    }
}
