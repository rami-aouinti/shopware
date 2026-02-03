<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Cache;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use function Pickware\ShopwareExtensionsBundle\VersionCheck\minimumShopwareVersion;
use Shopware\Core\Content\Product\SalesChannel\Detail\CachedProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\CachedProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;

class CacheInvalidationService
{
    /**
     * @var string[]
     */
    private array $cacheTagsToInvalidate = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly CacheInvalidator $cacheInvalidator,
    ) {}

    public function invalidateProductCache(array $productIds): void
    {
        // Invalidate the storefront api cache if the products stock or reserved stock was updated and in turn the
        // product availability was recalculated. For variant products the variant and main product cache need to be
        // invalidated.
        $parentIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(COALESCE(parent_id, id)))
                    FROM product
                    WHERE id in (:productIds) AND version_id = :version',
            [
                'productIds' => array_map('hex2bin', $productIds),
                'version' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'productIds' => ArrayParameterType::STRING,
            ],
        );

        $productIds = array_merge($productIds, $parentIds);

        $this->invalidateDetailRoute($productIds);
        $this->invalidateProductIds($productIds);
        $this->invalidateProductStreams($productIds);
        $this->invalidateProductListingRoute($productIds);
    }

    private function invalidateDetailRoute(array $productIds): void
    {
        if (minimumShopwareVersion('6.7')) {
            $this->invalidateTags(
                array_map(ProductDetailRoute::buildName(...), $productIds),
            );
        } else {
            $this->invalidateTags(
                array_map(CachedProductDetailRoute::buildName(...), $productIds),
            );
        }
    }

    private function invalidateProductIds(array $productIds): void
    {
        $this->invalidateTags(
            array_map(EntityCacheKeyGenerator::buildProductTag(...), $productIds),
        );
    }

    public function invalidateProductListingRoute(array $productIds): void
    {
        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(category_id)) as category_id
             FROM product_category_tree
             WHERE product_id IN (:ids)
             AND product_version_id = :version
             AND category_version_id = :version',
            [
                'ids' => array_map('hex2bin', $productIds),
                'version' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ],
        );

        if (count($ids) === 0) {
            return;
        }

        if (minimumShopwareVersion('6.7')) {
            $this->invalidateTags(
                array_map(ProductListingRoute::buildName(...), $ids),
            );
        } else {
            $this->invalidateTags(
                array_map(CachedProductListingRoute::buildName(...), $ids),
            );
        }
    }

    public function invalidateProductStreams(array $productIds): void
    {
        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(product_stream_id))
             FROM product_stream_mapping
             WHERE product_stream_mapping.product_id IN (:ids)
             AND product_stream_mapping.product_version_id = :version',
            [
                'ids' => array_map('hex2bin', $productIds),
                'version' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'ids' => ArrayParameterType::STRING,
            ],
        );

        if (count($ids) === 0) {
            return;
        }

        $this->invalidateTags(
            array_map(EntityCacheKeyGenerator::buildStreamTag(...), $ids),
        );
    }

    public function invalidateCacheDeferred(): void
    {
        $this->cacheInvalidator->invalidate($this->cacheTagsToInvalidate);
        $this->cacheTagsToInvalidate = [];
    }

    /**
     * @param string[] $tags
     */
    private function invalidateTags(array $tags): void
    {
        $this->cacheTagsToInvalidate = array_unique(
            array_merge($this->cacheTagsToInvalidate, $tags),
        );
    }
}
