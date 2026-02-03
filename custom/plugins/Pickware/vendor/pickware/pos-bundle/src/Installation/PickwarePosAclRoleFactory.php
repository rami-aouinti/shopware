<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Installation;

use Pickware\AclBundle\Acl\AclRoleFactory;
use Pickware\InstallationLibrary\AclRole\AclRole;
use Pickware\PickwarePos\Acl\PickwarePosFeaturePermissionsProvider;

class PickwarePosAclRoleFactory
{
    public const PICKWARE_POS_ROLE_NAME = 'Pickware POS User';

    public function __construct(
        private readonly PickwarePosFeaturePermissionsProvider $featurePermissionsProvider,
        private readonly AclRoleFactory $aclRoleFactory,
    ) {}

    public function createPickwarePosAclRole(): AclRole
    {
        return $this->aclRoleFactory->createAclRole(
            name: self::PICKWARE_POS_ROLE_NAME,
            featurePermissions: [
                // Only add the basic and default permissions to the role but not the setup permission to not grant
                // existing users access to the setup
                $this->featurePermissionsProvider->getSpecialBasicFeaturePermission(),
                ...$this->featurePermissionsProvider->getDefaultFeaturePermissions(),
            ],
            description: 'Diese Rolle enthält alle Rechte zur Nutzung der Pickware POS App',
        );
    }
}
