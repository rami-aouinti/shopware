<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\User;

use Error;
use Shopware\Core\System\User\UserEntity;

class UserExtension
{
    /**
     * The shopware user entity does not ensure that the admin property is set. So it is possible that the admin
     * property is null and calling isAdmin() on it will throw a TypeError.
     * Use this function instead. It will always return a boolean.
     */
    public static function isAdmin(UserEntity $user): bool
    {
        try {
            // @phpstan-ignore pickware.noDirectUserIsAdminCall
            return $user->isAdmin();
        } catch (Error) {
            // When the admin property on the user is null in the database and Shopware's entity hydrator initializes
            // the properties, it will skip the admin property as it is type-hinted as bool. When trying to access it
            // later via the isAdmin getter, this will cause an Error since the property is not initialized.
            return false;
        }
    }
}
