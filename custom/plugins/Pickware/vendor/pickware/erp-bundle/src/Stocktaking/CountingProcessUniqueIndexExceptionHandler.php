<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocktaking;

use Pickware\DalBundle\ExceptionHandling\UniqueIndexExceptionHandler;
use Pickware\DalBundle\ExceptionHandling\UniqueIndexExceptionMapping;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeCountingProcessDefinition;

class CountingProcessUniqueIndexExceptionHandler extends UniqueIndexExceptionHandler
{
    public const ERROR_CODE_STOCKTAKE_DUPLICATE_BIN_LOCATION = 'PICKWARE_ERP__STOCKTAKING__DUPLICATE_BIN_LOCATION';

    public function __construct()
    {
        parent::__construct([
            new UniqueIndexExceptionMapping(
                StocktakeCountingProcessDefinition::ENTITY_NAME,
                'pickware_stocktake_counting_process.uidx.bin_location_stock_take',
                self::ERROR_CODE_STOCKTAKE_DUPLICATE_BIN_LOCATION,
                [
                    'stocktake_id',
                    'bin_location_id',
                ],
            ),
        ]);
    }
}
