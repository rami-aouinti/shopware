<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Statistic\Service;

use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\AclRoleSnapshotGenerator;

/**
 * @phpstan-import-type AclRoleSnapshot from AclRoleSnapshotGenerator
 */
class StaticAdminRoleProvider
{
    public const FAKE_ROLE_ID = '00000000000000000000000000000000';

    /**
     * @return array{userRoleReferenceId: string, userRoleSnapshot: AclRoleSnapshot}
     */
    public function getAdminLogRole(): array
    {
        return [
            'userRoleReferenceId' => self::FAKE_ROLE_ID,
            'userRoleSnapshot' => [
                'name' => 'Admin',
            ],
        ];
    }
}
