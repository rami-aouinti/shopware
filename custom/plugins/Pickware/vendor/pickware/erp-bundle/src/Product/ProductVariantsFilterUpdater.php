<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Product;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityPostWriteValidationEvent;
use Pickware\DalBundle\EntityWriteValidationEventType;
use Pickware\DalBundle\Sql\SqlUuid;
use Shopware\Core\System\User\UserDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductVariantsFilterUpdater implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public static function getSubscribedEvents()
    {
        return [
            EntityWriteValidationEventType::Post->getEventName(UserDefinition::ENTITY_NAME) => 'updateProductVariantsFilterInUserConfig',
        ];
    }

    /**
     * Updates the user config for the "pwErpShowVariantsFilter" filter in the product grid.
     * This is necessary to ensure that the filter is activated for all created users after the migration
     */
    public function updateProductVariantsFilterInUserConfig(EntityPostWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if ($command->getEntityExistence()->exists()) {
                continue;
            }

            $userId = $command->getPrimaryKey()['id'];

            if (!$userId) {
                continue;
            }

            $this->connection->executeStatement(
                'INSERT INTO `user_config` (`id`, `user_id`, `key`, `value`, `created_at`)
                VALUES (
                    ' . SqlUuid::UUID_V4_GENERATION . ',
                    :userId,
                    "grid.filter.product",
                    JSON_OBJECT(
                        "pwErpShowVariantsFilter",
                        JSON_OBJECT(
                            "value", TRUE,
                            "criteria", JSON_ARRAY(
                                JSON_OBJECT(
                                    "type", "equals",
                                    "field", "pwErpShowVariantsFilter",
                                    "value", TRUE
                                )
                            )
                        )
                    ),
                    UTC_TIMESTAMP(3)
                )',
                [
                    'userId' => $userId,
                ],
            );
        }
    }
}
