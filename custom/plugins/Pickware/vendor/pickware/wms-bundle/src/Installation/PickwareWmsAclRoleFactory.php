<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Installation;

use Pickware\AclBundle\Acl\AclRoleFactory;
use Pickware\InstallationLibrary\AclRole\AclRole;
use Pickware\PickwareWms\Acl\PickwareWmsFeaturePermissionsProvider;

class PickwareWmsAclRoleFactory
{
    public const PICKWARE_WMS_ROLE_NAME = 'Pickware WMS User';

    public function __construct(
        private readonly PickwareWmsFeaturePermissionsProvider $featurePermissionsProvider,
        private readonly AclRoleFactory $aclRoleFactory,
    ) {}

    public function createPickwareWmsAclRole(): AclRole
    {
        return $this->aclRoleFactory->createAclRole(
            name: self::PICKWARE_WMS_ROLE_NAME,
            featurePermissions: [
                // Only add the basic and default permissions to the role but not the setup permission to not grant
                // existing users access to the setup
                $this->featurePermissionsProvider->getSpecialBasicFeaturePermission(),
                ...$this->featurePermissionsProvider->getDefaultFeaturePermissions(),
            ],
            description: 'Diese Rolle enthält alle Rechte zur Nutzung der Pickware WMS App',
        );
    }
}
