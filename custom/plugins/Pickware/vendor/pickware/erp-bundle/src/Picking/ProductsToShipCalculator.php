<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picking;

use Pickware\DalBundle\EntityManager;

/**
 * This service is required for backwards compatibility with WMS
 * Can be removed after WMS minimum requirement of ERP-4.4.0
 */
class ProductsToShipCalculator extends OrderQuantitiesToShipCalculator
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {
        parent::__construct($entityManager);
    }
}
