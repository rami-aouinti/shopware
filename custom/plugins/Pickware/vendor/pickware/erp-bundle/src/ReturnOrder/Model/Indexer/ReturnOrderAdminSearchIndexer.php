<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ReturnOrder\Model\Indexer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\Elasticsearch\AbstractIndexer;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('shopware.elastic.admin-searcher-index', attributes: ['key' => ReturnOrderDefinition::ENTITY_NAME])]
class ReturnOrderAdminSearchIndexer extends AbstractIndexer
{
    public function __construct(
        Connection $connection,
        IteratorFactory $factory,
        #[Autowire(service: 'pickware_erp_return_order.repository')]
        EntityRepository $repository,
        #[Autowire('%elasticsearch.indexing_batch_size%')]
        int $indexingBatchSize,
    ) {
        parent::__construct($connection, $factory, $repository, $indexingBatchSize, ReturnOrderDefinition::ENTITY_NAME);
    }

    public function getQuery(array $ids): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT
                LOWER(HEX(`pickware_erp_return_order`.`id`)) AS `id`,
                `number`
            FROM `pickware_erp_return_order`
            WHERE `pickware_erp_return_order`.`id` IN (:ids)',
            [
                'ids' => array_map('hex2bin', $ids),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ],
        );
    }
}
