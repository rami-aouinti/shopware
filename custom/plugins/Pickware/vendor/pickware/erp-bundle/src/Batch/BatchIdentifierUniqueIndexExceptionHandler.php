<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Batch;

use Pickware\DalBundle\ExceptionHandling\UniqueIndexExceptionHandler;
use Pickware\DalBundle\ExceptionHandling\UniqueIndexExceptionMapping;
use Pickware\DalBundle\Translation;
use Pickware\PickwareErpStarter\Batch\Model\BatchDefinition;

class BatchIdentifierUniqueIndexExceptionHandler extends UniqueIndexExceptionHandler
{
    public const ERROR_CODE = 'PICKWARE_ERP__BATCH__DUPLICATE_IDENTIFIER';

    public function __construct()
    {
        parent::__construct([
            new UniqueIndexExceptionMapping(
                entityName: BatchDefinition::ENTITY_NAME,
                uniqueIndexName: 'pickware_erp_batch.uidx.product_unique_identifier',
                errorCodeToAssign: self::ERROR_CODE,
                fields: [
                    'uniqueIdentifier',
                    'productId',
                ],
                detailMessage: new Translation(
                    german: 'Es gibt bereits eine andere Charge mit diesem Identifikator für dieses Produkt. Bitte verwende ein anderes MHD oder eine andere Nummer.',
                    english: 'There already is another batch with this identifier for this product. Please use a different BBD or a different number.',
                ),
            ),
        ]);
    }
}
