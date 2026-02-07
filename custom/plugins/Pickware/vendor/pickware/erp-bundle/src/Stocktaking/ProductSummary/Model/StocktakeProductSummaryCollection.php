<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocktaking\ProductSummary\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(StocktakeProductSummaryEntity $entity)
 * @method void set(string $key, StocktakeProductSummaryEntity $entity)
 * @method StocktakeProductSummaryEntity[] getIterator()
 * @method StocktakeProductSummaryEntity[] getElements()
 * @method StocktakeProductSummaryEntity|null get(string $key)
 * @method StocktakeProductSummaryEntity|null first()
 * @method StocktakeProductSummaryEntity|null last()
 */
class StocktakeProductSummaryCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StocktakeProductSummaryEntity::class;
    }
}
