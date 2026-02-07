<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Warehouse\Model\Indexer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\Elasticsearch\AbstractIndexer;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('shopware.elastic.admin-searcher-index', attributes: ['key' => WarehouseDefinition::ENTITY_NAME])]
class WarehouseAdminSearchIndexer extends AbstractIndexer
{
    public function __construct(
        Connection $connection,
        IteratorFactory $factory,
        #[Autowire(service: 'pickware_erp_warehouse.repository')]
        EntityRepository $repository,
        #[Autowire('%elasticsearch.indexing_batch_size%')]
        int $indexingBatchSize,
    ) {
        parent::__construct($connection, $factory, $repository, $indexingBatchSize, WarehouseDefinition::ENTITY_NAME);
    }

    public function getQuery(array $ids): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT
                LOWER(HEX(`pickware_erp_warehouse`.`id`)) AS `id`,
                `name`,
                `code`
            FROM `pickware_erp_warehouse`
            WHERE `pickware_erp_warehouse`.`id` IN (:ids)',
            [
                'ids' => array_map('hex2bin', $ids),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ],
        );
    }
}
