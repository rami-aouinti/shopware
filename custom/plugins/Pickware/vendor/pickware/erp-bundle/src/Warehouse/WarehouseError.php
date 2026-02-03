<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Warehouse;

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;

class WarehouseError
{
    private const JSON_ERROR_CODE_NAMESPACE = 'PICKWARE_ERP__WAREHOUSE__';
    private const JSON_ERROR_CANNOT_BE_DELETED_DUE_TO_EXISTING_STOCK = self::JSON_ERROR_CODE_NAMESPACE . 'CANNOT_BE_DELETED_DUE_TO_EXISTING_STOCK';

    public static function cannotBeDeletedDueToExistingStock(string $warehouseId): LocalizableJsonApiError
    {
        return new LocalizableJsonApiError([
            'code' => self::JSON_ERROR_CANNOT_BE_DELETED_DUE_TO_EXISTING_STOCK,
            'title' => [
                'en' => 'Warehouse cannot be deleted',
                'de' => 'Lager kann nicht gelöscht werden',
            ],
            'detail' => [
                'en' => 'The warehouse cannot be deleted because there is still stock in the warehouse (e.g. in bin locations or in a picking process).',
                'de' => 'Das Lager kann nicht gelöscht werden, da sich noch Bestand im Lager befindet (z.B. auf Lagerplätzen oder in einem Kommissionierprozess)',
            ],
            'meta' => [
                'warehouseId' => $warehouseId,
            ],
        ]);
    }
}
