<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder\ImportExportProfile;

use InvalidArgumentException;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SupplierOrderExportColumnService
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
    ) {}

    /**
     * @param array<string,mixed> $exportConfig
     * @return string[]
     */
    public function getColumns(array $exportConfig): array
    {
        $systemConfigColumnIdentifiers = $this->systemConfigService->get(SupplierOrderExporter::SYSTEM_CONFIG_CSV_EXPORT_COLUMNS_KEY);
        $systemConfigColumns = array_map(fn($identifier) => SupplierOrderExporter::COLUMN_IDENTIFIER_MAPPING[$identifier], $systemConfigColumnIdentifiers ?? []);
        $exportScenario = isset($exportConfig['supplierOrderExportScenario']) ? SupplierOrderExportScenario::tryFrom($exportConfig['supplierOrderExportScenario']) : null;

        if ($exportScenario === null) {
            return SupplierOrderExporter::GRID_EXPORT_DEFAULT_COLUMNS;
        }

        return match ($exportScenario) {
            SupplierOrderExportScenario::GridExport => $exportConfig['columns'] ?? SupplierOrderExporter::GRID_EXPORT_DEFAULT_COLUMNS,
            SupplierOrderExportScenario::EmailCsvExport => count($systemConfigColumns) > 0 ? $systemConfigColumns : throw new InvalidArgumentException('No columns configured for email csv export'),
        };
    }
}
