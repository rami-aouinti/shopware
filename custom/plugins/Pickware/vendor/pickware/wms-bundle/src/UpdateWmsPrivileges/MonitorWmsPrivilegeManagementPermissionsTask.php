<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\UpdateWmsPrivileges;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class MonitorWmsPrivilegeManagementPermissionsTask extends ScheduledTask
{
    private const DEFAULT_INTERVAL_SECONDS = 24 * 60 * 60; // 24 hours

    public static function getTaskName(): string
    {
        return 'pickware.wms_bundle.monitor_wms_privilege_management_permissions';
    }

    public static function getDefaultInterval(): int
    {
        return self::DEFAULT_INTERVAL_SECONDS;
    }
}
