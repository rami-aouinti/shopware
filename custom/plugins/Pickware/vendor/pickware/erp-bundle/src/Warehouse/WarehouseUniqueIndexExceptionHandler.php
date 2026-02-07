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

use Pickware\DalBundle\ExceptionHandling\UniqueIndexExceptionHandler;
use Pickware\DalBundle\ExceptionHandling\UniqueIndexExceptionMapping;
use Pickware\DalBundle\Translation;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;

class WarehouseUniqueIndexExceptionHandler extends UniqueIndexExceptionHandler
{
    public function __construct()
    {
        parent::__construct([
            new UniqueIndexExceptionMapping(
                WarehouseDefinition::ENTITY_NAME,
                'pickware_erp_warehouse.uidx.code',
                'PICKWARE_ERP__WAREHOUSE__DUPLICATE_WAREHOUSE_CODE',
                ['code'],
            ),
            new UniqueIndexExceptionMapping(
                WarehouseDefinition::ENTITY_NAME,
                'pickware_erp_warehouse.uidx.name',
                'PICKWARE_ERP__WAREHOUSE__DUPLICATE_WAREHOUSE_NAME',
                ['name'],
            ),
            new UniqueIndexExceptionMapping(
                BinLocationDefinition::ENTITY_NAME,
                'pickware_erp_bin_location.uidx.code',
                'PICKWARE_ERP__WAREHOUSE__DUPLICATE_BIN_LOCATION_CODE',
                ['code'],
                new Translation(
                    german: 'Es gibt bereits einen anderen Lagerplatz mit dieser Bezeichnung. Bitte verwende eine andere Bezeichnung.',
                    english: 'There is another bin location with this name. Please use a different name.',
                ),
            ),
        ]);
    }
}
