<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\AclBundle\Acl;

use Pickware\AclBundle\FeaturePermission\FeaturePermission;
use Pickware\InstallationLibrary\AclRole\AclRole;

class AclRoleFactory
{
    public const SHOPWARE_PERMISSION_ROLES = [
        'creator',
        'deleter',
        'editor',
        'viewer',
    ];

    /**
     * @param FeaturePermission[] $featurePermissions
     */
    public function createAclRole(string $name, array $featurePermissions, ?string $description = null): AclRole
    {
        $allPrivileges = array_merge(
            ...array_map(
                fn(FeaturePermission $featurePermission) => $this->createFeaturePermissionPrivileges($featurePermission),
                $featurePermissions,
            ),
        );

        return new AclRole(
            name: $name,
            privileges: array_unique($allPrivileges),
            description: $description,
        );
    }

    /**
     * @return string[]
     */
    public function createFeaturePermissionPrivileges(FeaturePermission $featurePermission): array
    {
        return [
            $featurePermission->getTechnicalName(),
            ...$this->createShopwarePermissionRolePrivileges($featurePermission),
            ...$featurePermission->getPrivileges(),
        ];
    }

    /**
     * @return string[]
     */
    public function createShopwarePermissionRolePrivileges(FeaturePermission $featurePermission): array
    {
        return array_map(
            fn(string $privilege) => sprintf(
                '%1$s.%2$s',
                $featurePermission->getTechnicalName(),
                $privilege,
            ),
            self::SHOPWARE_PERMISSION_ROLES,
        );
    }
}
