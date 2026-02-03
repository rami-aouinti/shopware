<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class TruncateImportExportLogEntriesTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'pickware.erp.truncate_import_export_log_entries';
    }

    public static function getDefaultInterval(): int
    {
        // This task should run once a day.
        return 60 * 60 * 24;
    }
}
