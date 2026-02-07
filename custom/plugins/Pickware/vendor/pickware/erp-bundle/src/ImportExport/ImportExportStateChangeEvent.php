<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport;

use Shopware\Core\Framework\Context;

class ImportExportStateChangeEvent
{
    public const EVENT_NAME = 'pickware_erp.import_export.state_change';

    private string $importExportId;
    private string $importExportType;
    private string $profileTechnicalName;
    private string $fromState;
    private string $toState;
    private Context $context;

    public function __construct(
        string $importExportId,
        string $importExportType,
        string $profileTechnicalName,
        string $fromState,
        string $toState,
        Context $context,
    ) {
        $this->importExportId = $importExportId;
        $this->importExportType = $importExportType;
        $this->profileTechnicalName = $profileTechnicalName;
        $this->fromState = $fromState;
        $this->toState = $toState;
        $this->context = $context;
    }

    public function getImportExportId(): string
    {
        return $this->importExportId;
    }

    public function getImportExportType(): string
    {
        return $this->importExportType;
    }

    public function getProfileTechnicalName(): string
    {
        return $this->profileTechnicalName;
    }

    public function getFromState(): string
    {
        return $this->fromState;
    }

    public function getToState(): string
    {
        return $this->toState;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
