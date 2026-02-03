<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\Model\ExceptionHandler;

use Pickware\DalBundle\ExceptionHandling\UniqueIndexExceptionHandler;
use Pickware\DalBundle\ExceptionHandling\UniqueIndexExceptionMapping;
use Pickware\PickwareErpStarter\Stock\Model\StockContainerDefinition;

class StockContainerUniqueIndexExceptionHandler extends UniqueIndexExceptionHandler
{
    public const EXCEPTION_ERROR_CODE = 'PICKWARE_ERP__STOCK__DUPLICATE_STOCK_CONTAINER_NUMBER';

    public function __construct()
    {
        parent::__construct([
            new UniqueIndexExceptionMapping(
                StockContainerDefinition::ENTITY_NAME,
                'pickware_erp_stock_container.uidx.number',
                self::EXCEPTION_ERROR_CODE,
                ['number'],
            ),
        ]);
    }
}
