<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockMovementProcess;

use Doctrine\DBAL\Connection;
use JetBrains\PhpStorm\Deprecated;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\StockMovementProcess\Model\StockMovementProcessType;
use Pickware\PickwareErpStarter\StockMovementProcess\Model\StockMovementProcessTypeDefinition;
use Shopware\Core\Framework\Context;

class StockMovementProcessTypeInstaller
{
    public function __construct(
        #[Deprecated(reason: 'Will be removed in 5.0.0. Provide the constructor argument $db instead.')]
        private readonly ?EntityManager $entityManager = null,
        private readonly ?Connection $db = null,
    ) {}

    public function installStockMovementProcessType(StockMovementProcessType $stockMovementProcessType, Context $context): void
    {
        if ($this->db !== null) {
            $sql = <<<SQL
                    INSERT INTO `pickware_erp_stock_movement_process_type`
                        (`technical_name`, `referenced_entity_field_name`, `referenced_entity_definition_class`, `created_at`)
                    VALUES (:technicalName, :referencedEntityFieldName, :referencedEntityDefinitionClass, UTC_TIMESTAMP(3))
                    ON DUPLICATE KEY UPDATE
                        `referenced_entity_field_name` = VALUES(`referenced_entity_field_name`),
                        `referenced_entity_definition_class` = VALUES(`referenced_entity_definition_class`),
                        `updated_at` = UTC_TIMESTAMP(3)
                SQL;

            $this->db->executeStatement($sql, [
                'technicalName' => $stockMovementProcessType->getTechnicalName(),
                'referencedEntityFieldName' => $stockMovementProcessType->getReferencedEntityFieldName(),
                'referencedEntityDefinitionClass' => $stockMovementProcessType->getReferencedEntityDefinitionClass(),
            ]);
        } else {
            // This code path is only kept for backwards compatibility with the Shopify integration, where this installer
            // is initialized with the entity manager instead of the connection.
            $this->entityManager->upsert(
                StockMovementProcessTypeDefinition::class,
                [
                    [
                        'technicalName' => $stockMovementProcessType->getTechnicalName(),
                        'referencedEntityFieldName' => $stockMovementProcessType->getReferencedEntityFieldName(),
                        'referencedEntityDefinitionClass' => $stockMovementProcessType->getReferencedEntityDefinitionClass(),
                    ],
                ],
                $context,
            );
        }
    }
}
