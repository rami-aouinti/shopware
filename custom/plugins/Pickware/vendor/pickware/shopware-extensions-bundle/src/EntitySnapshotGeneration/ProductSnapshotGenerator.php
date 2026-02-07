<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration;

use Pickware\DalBundle\EntityManager;
use Pickware\ShopwareExtensionsBundle\Product\ProductNameFormatterService;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;

/**
 * @phpstan-type ProductSnapshot array{productNumber: string, name: ?string, ean: ?string}
 * @implements EntitySnapshotGenerator<ProductSnapshot>
 */
#[AsEntitySnapshotGenerator(entityClass: ProductDefinition::class)]
class ProductSnapshotGenerator implements EntitySnapshotGenerator
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ProductNameFormatterService $productNameFormatterService,
    ) {}

    public function generateSnapshots(array $ids, Context $context): array
    {
        $productNames = $this->productNameFormatterService->getFormattedProductNames(
            productIds: $ids,
            templateOptions: [],
            context: $context,
        );

        return $this->entityManager->findBy(
            ProductDefinition::class,
            ['id' => $ids],
            $context,
        )->map(fn(ProductEntity $product) => [
            'productNumber' => $product->getProductNumber(),
            'name' => $productNames[$product->getId()],
            'ean' => $product->getEan(),
        ]);
    }
}
