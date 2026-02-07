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

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityPostWriteValidationEvent;
use Pickware\DalBundle\EntityWriteValidationEventType;
use Pickware\DalBundle\Sql\SqlUuid;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PickwareProductInitializer implements EventSubscriberInterface
{
    public const SUBSCRIBER_PRIORITY = 0;

    public function __construct(
        private readonly Connection $db,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteValidationEventType::Post->getEventName(ProductDefinition::ENTITY_NAME) => [
                'productPostWriteValidation',
                self::SUBSCRIBER_PRIORITY,
            ],
        ];
    }

    public function productPostWriteValidation(EntityPostWriteValidationEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $productIds = ImmutableCollection::create($event->getCommands())
            ->filter(fn(WriteCommand $writeCommand) => $writeCommand instanceof InsertCommand)
            ->map(fn(WriteCommand $writeCommand) => $writeCommand->getPrimaryKey()['id'])
            ->map(bin2hex(...))
            ->asArray();

        $this->ensurePickwareProductsExist($productIds, true);
    }

    /**
     * @param list<string> $productIds
     */
    public function ensurePickwareProductsExist(array $productIds, ?bool $productInserted = false): void
    {
        if (count($productIds) === 0) {
            return;
        }

        $this->db->executeStatement(
            'INSERT INTO `pickware_erp_pickware_product` (
                id,
                product_id,
                product_version_id,
                internal_reserved_stock,
                external_reserved_stock,
                physical_stock,
                stock_not_available_for_sale,
                incoming_stock,
                is_stock_management_disabled,
                ship_automatically,
                created_at
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                product.id,
                product.version_id,
                0,
                0,
                0,
                0,
                0,
                0,
                0,
                UTC_TIMESTAMP(3)
            FROM `product`
            WHERE `product`.`id` IN (:ids) AND `product`.`version_id` = :liveVersionId
            ON DUPLICATE KEY UPDATE `pickware_erp_pickware_product`.`id` = `pickware_erp_pickware_product`.`id`',
            [
                'ids' => array_map('hex2bin', $productIds),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'ids' => ArrayParameterType::STRING,
            ],
        );

        if ($productInserted) {
            // If a product gets cloned, the pickwareProduct extension gets cloned as well. We want that for certain
            // values of the pickwareProduct, e.g. reorderPoint or isStockManagementDisabled. Other values are
            // set to their default values by executing this query.
            $this->db->executeStatement(
                'UPDATE `pickware_erp_pickware_product`
                SET `pickware_erp_pickware_product`.`internal_reserved_stock` = 0,
                    `pickware_erp_pickware_product`.`external_reserved_stock` = 0,
                    `pickware_erp_pickware_product`.`physical_stock` = 0,
                    `pickware_erp_pickware_product`.`incoming_stock` = 0,
                    `pickware_erp_pickware_product`.`stock_not_available_for_sale` = 0
                WHERE `pickware_erp_pickware_product`.`product_id` IN (:productIds)
                AND `pickware_erp_pickware_product`.`product_version_id` = :liveVersionId;',
                [
                    'productIds' => array_map('hex2bin', $productIds),
                    'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                ],
                [
                    'productIds' => ArrayParameterType::STRING,
                ],
            );

            $this->eventDispatcher->dispatch(new PickwareProductInsertedEvent($productIds));
        }
    }

    public function ensurePickwareProductsExistForAllProducts(): void
    {
        RetryableTransaction::retryable($this->db, function(): void {
            $this->db->executeStatement(
                'INSERT INTO `pickware_erp_pickware_product` (
                id,
                product_id,
                product_version_id,
                physical_stock,
                stock_not_available_for_sale,
                incoming_stock,
                is_stock_management_disabled,
                ship_automatically,
                created_at
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                product.id,
                product.version_id,
                0,
                0,
                0,
                0,
                0,
                UTC_TIMESTAMP(3)
            FROM `product`
            WHERE `product`.`version_id` = :liveVersionId
            ON DUPLICATE KEY UPDATE `pickware_erp_pickware_product`.`id` = `pickware_erp_pickware_product`.`id`',
                ['liveVersionId' => hex2bin(Defaults::LIVE_VERSION)],
            );
        });
    }
}
