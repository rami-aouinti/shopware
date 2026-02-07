<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\MobileAppAuthBundle\Installation;

use Doctrine\DBAL\Connection;
use Pickware\MobileAppAuthBundle\Installation\Steps\UpsertMobileAppAclRoleInstallationStep;

class PickwareMobileAppAuthBundleInstaller
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function install(): void
    {
        (new UpsertMobileAppAclRoleInstallationStep($this->db))->install();
    }
}
