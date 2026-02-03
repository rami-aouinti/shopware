<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PurchaseList;

use Pickware\DalBundle\ExceptionHandling\UniqueIndexExceptionHandler;
use Pickware\DalBundle\ExceptionHandling\UniqueIndexExceptionMapping;
use Pickware\PickwareErpStarter\PurchaseList\Model\PurchaseListItemDefinition;

class PurchaseListItemIndexExceptionHandler extends UniqueIndexExceptionHandler
{
    public function __construct()
    {
        parent::__construct([
            new UniqueIndexExceptionMapping(
                PurchaseListItemDefinition::ENTITY_NAME,
                'pickware_erp_purchase_list_item.uidx.product',
                'PICKWARE_ERP__PURCHASE_LIST_ITEM__DUPLICATE_PRODUCT',
                ['product_id'],
            ),
            new UniqueIndexExceptionMapping(
                PurchaseListItemDefinition::ENTITY_NAME,
                'pickware_erp_purchase_list_item.uidx.product_supplier_conf',
                'PICKWARE_ERP__PURCHASE_LIST_ITEM__DUPLICATE_PRODUCT_SUPPLIER_CONFIGURATION',
                ['product_supplier_configuration_id'],
            ),
        ]);
    }
}
