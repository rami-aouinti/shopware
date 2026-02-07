<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Migration;

use Doctrine\DBAL\Connection;
use Pickware\AclBundle\Acl\AclRoleFactory;
use Pickware\PickwareWms\Acl\PickwareWmsFeaturePermissionsProvider;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1715954882RenameWmsAppFeaturePrivileges extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1715954882;
    }

    public function update(Connection $connection): void
    {
        foreach (PickwareWmsFeaturePermissionsProvider::LEGACY_FEATURE_PRIVILEGES as $legacyPrivilege) {
            $newPrivilege = str_replace('pickware_wms.', 'pickware_wms_app_', $legacyPrivilege);
            $replacementString = implode(
                ', ',
                array_map(
                    fn(string $privilege) => sprintf('"%s"', $privilege),
                    [
                        $legacyPrivilege,
                        $newPrivilege,
                        ...array_map(
                            fn(string $role) => sprintf('%1$s.%2$s', $newPrivilege, $role),
                            AclRoleFactory::SHOPWARE_PERMISSION_ROLES,
                        ),
                    ],
                ),
            );
            $connection->executeStatement(
                <<<SQL
                    UPDATE `acl_role`
                    SET `privileges` = REPLACE(`privileges`, :searchString, :replacementString)
                    WHERE
                        `privileges` LIKE :legacyPrivilegeQuery
                        AND `privileges` NOT LIKE :newPrivilegeQuery
                    SQL,
                [
                    'searchString' => sprintf('"%s"', $legacyPrivilege),
                    'replacementString' => $replacementString,
                    'legacyPrivilegeQuery' => sprintf('%%"%s"%%', $legacyPrivilege),
                    'newPrivilegeQuery' => sprintf('%%"%s"%%', $newPrivilege),
                ],
            );
        }
    }

    public function updateDestructive(Connection $connection): void {}
}
