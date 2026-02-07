<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\Exception;

class ImporterServiceDoesNotExistException extends ImportExportException
{
    public function __construct(string $profileTechnicalName)
    {
        parent::__construct(sprintf(
            'No importer service found for profile "%s". Maybe this profile does not provide an importer or the ' .
            'plugin providing this importer is not installed or active anymore.',
            $profileTechnicalName,
        ));
    }
}
