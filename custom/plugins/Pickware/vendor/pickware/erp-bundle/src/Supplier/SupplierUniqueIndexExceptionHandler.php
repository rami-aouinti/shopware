<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Supplier;

use Pickware\DalBundle\ExceptionHandling\UniqueIndexExceptionHandler;
use Pickware\DalBundle\ExceptionHandling\UniqueIndexExceptionMapping;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierDefinition;

class SupplierUniqueIndexExceptionHandler extends UniqueIndexExceptionHandler
{
    public function __construct()
    {
        parent::__construct([
            new UniqueIndexExceptionMapping(
                SupplierDefinition::ENTITY_NAME,
                'pickware_erp_supplier.uidx.code',
                'PICKWARE_ERP__SUPPLIER__DUPLICATE_SUPPLIER_NUMBER',
                ['number'],
            ),
            new UniqueIndexExceptionMapping(
                SupplierDefinition::ENTITY_NAME,
                'pickware_erp_supplier.uidx.name',
                'PICKWARE_ERP__SUPPLIER__DUPLICATE_SUPPLIER_NAME',
                ['name'],
            ),
        ]);
    }
}
