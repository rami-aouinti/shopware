<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProfile\Indexer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\Elasticsearch\AbstractIndexer;
use Pickware\PickwareWms\PickingProfile\Model\PickingProfileDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('shopware.elastic.admin-searcher-index', attributes: ['key' => PickingProfileDefinition::ENTITY_NAME])]
class PickingProfileAdminSearchIndexer extends AbstractIndexer
{
    public function __construct(
        Connection $connection,
        IteratorFactory $factory,
        #[Autowire(service: 'pickware_wms_picking_profile.repository')]
        EntityRepository $repository,
        #[Autowire('%elasticsearch.indexing_batch_size%')]
        int $indexingBatchSize,
    ) {
        parent::__construct($connection, $factory, $repository, $indexingBatchSize, PickingProfileDefinition::ENTITY_NAME);
    }

    public function getQuery(array $ids): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT
                LOWER(HEX(`pickware_wms_picking_profile`.`id`)) AS `id`,
                `name`
            FROM `pickware_wms_picking_profile`
            WHERE `pickware_wms_picking_profile`.`id` IN (:ids)',
            [
                'ids' => array_map('hex2bin', $ids),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ],
        );
    }
}
