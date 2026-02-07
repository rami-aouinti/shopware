<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary\Elasticsearch;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Event\RefreshIndexEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ElasticsearchIndexInstaller
{
    private Connection $db;
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(Connection $db, EventDispatcherInterface $eventDispatcher)
    {
        $this->db = $db;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function installElasticsearchIndices(array $entities): void
    {
        if (!EntityDefinitionQueryHelper::tableExists($this->db, 'admin_elasticsearch_index_task')) {
            return;
        }

        $existingIndices = $this->db->fetchFirstColumn('SELECT entity FROM `admin_elasticsearch_index_task`');
        $entitiesToIndex = array_values(array_diff($entities, $existingIndices));

        if (!empty($entitiesToIndex)) {
            $this->eventDispatcher->dispatch(new RefreshIndexEvent(false, [], $entitiesToIndex));
        }
    }
}
