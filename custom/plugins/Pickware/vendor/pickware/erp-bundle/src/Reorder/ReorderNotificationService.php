<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Reorder;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\ShopwareExtensionsBundle\Product\ProductNameFormatterService;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ReorderNotificationService
{
    public function __construct(
        private readonly Connection $db,
        private readonly EntityManager $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ProductNameFormatterService $productNameFormatterService,
    ) {}

    public function sendReorderNotification(Context $context): void
    {
        $products = $this->getReorderProducts($context);
        if ($products->count() === 0) {
            return;
        }

        $this->eventDispatcher->dispatch(
            new ReorderMailEvent($context, $products),
            ReorderMailEvent::EVENT_NAME,
        );
    }

    private function getReorderProducts(Context $context): ProductCollection
    {
        $productResult = $this->db->fetchAllAssociative(
            <<<SQL
                    SELECT
                        product.id AS id,
                        product.version_id as versionId
                    FROM product
                    LEFT JOIN product parent
                        ON parent.id = product.parent_id
                        AND parent.version_id = product.version_id
                    LEFT JOIN pickware_erp_pickware_product AS pickwareProduct
                        ON pickwareProduct.product_id = product.id
                        AND pickwareProduct.product_version_id = product.version_id
                    WHERE
                        product.version_id = :liveVersionId
                        AND COALESCE(product.active, parent.active) = 1
                        AND pickwareProduct.is_excluded_from_reorder_notification_mail = 0
                        AND product.stock <= pickwareProduct.reorder_point
                        AND pickwareProduct.is_stock_management_disabled = 0
                        # Do not list parent products
                        AND IFNULL(product.child_count, 0) = 0
                SQL,
            [
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
        );
        if (count($productResult) === 0) {
            return new ProductCollection();
        }

        $criteria = (new Criteria())
            ->addFilter(
                new EqualsAnyFilter('id', array_map('bin2hex', array_column($productResult, 'id'))),
                new EqualsAnyFilter('versionId', array_map('bin2hex', array_column($productResult, 'versionId'))),
            )
            ->addAssociations([
                'pickwareErpPickwareProduct',
                'options',
            ])
            ->addSorting(new FieldSorting('name'));

        $productNames = [];
        $reorderProducts = $context->enableInheritance(function(Context $inheritanceContext) use ($criteria, &$productNames) {
            // Fetch ids to format names before fetching the full products to reduce memory usage peak
            $productIds = $this->entityManager->findIdsBy(ProductDefinition::class, $criteria, $inheritanceContext);
            $productNames = $this->productNameFormatterService->getFormattedProductNames($productIds, [], $inheritanceContext);

            return $this->entityManager->findBy(ProductDefinition::class, $criteria, $inheritanceContext);
        });
        foreach ($reorderProducts as $reorderProduct) {
            $reorderProduct->setName($productNames[$reorderProduct->getId()]);
        }

        return new ProductCollection($reorderProducts);
    }
}
