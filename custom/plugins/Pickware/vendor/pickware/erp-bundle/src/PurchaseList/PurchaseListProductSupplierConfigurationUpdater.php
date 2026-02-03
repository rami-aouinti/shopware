<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PurchaseList;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\PurchaseList\Model\PurchaseListItemDefinition;
use Pickware\PickwareErpStarter\Supplier\DefaultSupplier\DefaultSupplierInitialySetEvent;
use Pickware\PickwareErpStarter\Supplier\DefaultSupplier\DefaultSupplierUpdatedEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PurchaseListProductSupplierConfigurationUpdater implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Connection $connection,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            DefaultSupplierUpdatedEvent::class => 'onDefaultSupplierUpdated',
            DefaultSupplierInitialySetEvent::class => 'onDefaultSupplierUpdated',
            PurchaseListItemDefinition::ENTITY_WRITTEN_EVENT => (
                'assignProductDefaultSupplierConfigurationToPurchaseListItemOnPurchaseListItemUpdate'
            ),
        ];
    }

    /**
     * Products can be added to the purchase list even if they don't have any supplier configurations yet. If a product
     * is updated and a new default supplier is set, we assign that default supplier configuration to the product's
     * purchase list item.
     */
    public function onDefaultSupplierUpdated(DefaultSupplierUpdatedEvent|DefaultSupplierInitialySetEvent $event): void
    {
        $this->setProductDefaultSupplierConfigurationForPurchaseListItem($event->getProductIds());
    }

    /**
     * A purchase list item should always have a product supplier configuration assigned, if at least one supplier is
     * configured for the respective product. If a product's supplier configuration is deleted and was configured in a
     * purchase list item, we assign the product's default supplier configuration to that purchase list item, if a
     * default supplier is configured.
     */
    public function assignProductDefaultSupplierConfigurationToPurchaseListItemOnPurchaseListItemUpdate(
        EntityWrittenEvent $event,
    ): void {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        /** @var string[] $idsOfPurchaseListItemsWithoutSupplier */
        $idsOfPurchaseListItemsWithoutSupplier = (new ImmutableCollection($event->getWriteResults()))
            ->filter(fn(EntityWriteResult $writeResult) => $writeResult->getOperation() === EntityWriteResult::OPERATION_UPDATE)
            ->filter(fn(EntityWriteResult $writeResult) => array_key_exists('productSupplierConfigurationId', $writeResult->getPayload()))
            ->filter(fn(EntityWriteResult $writeResult) => $writeResult->getPayload()['productSupplierConfigurationId'] === null)
            ->map(fn(EntityWriteResult $writeResult) => $writeResult->getPrimaryKey())
            ->asArray();
        if (count($idsOfPurchaseListItemsWithoutSupplier) === 0) {
            return;
        }

        $productIdsOfPurchaseListItemsWithoutSupplier = $this->entityManager->findIdsBy(
            ProductDefinition::class,
            ['pickwareErpPurchaseListItems.id' => $idsOfPurchaseListItemsWithoutSupplier],
            $event->getContext(),
        );

        $this->setProductDefaultSupplierConfigurationForPurchaseListItem($productIdsOfPurchaseListItemsWithoutSupplier);
    }

    private function setProductDefaultSupplierConfigurationForPurchaseListItem(array $productIds): void
    {
        if (count($productIds) === 0) {
            return;
        }

        $this->connection->executeStatement(
            <<<SQL
                UPDATE `pickware_erp_purchase_list_item` as `purchaseListItem`
                INNER JOIN `pickware_erp_pickware_product` as `pickwareProduct`
                    ON `purchaseListItem`.`product_id` = `pickwareProduct`.`product_id`
                    AND `purchaseListItem`.`product_version_id` = `pickwareProduct`.`product_version_id`
                INNER JOIN `pickware_erp_product_supplier_configuration` as `productDefaultSupplierConfiguration`
                    ON `pickwareProduct`.`product_id` = `productDefaultSupplierConfiguration`.`product_id`
                    AND `pickwareProduct`.`product_version_id` = `productDefaultSupplierConfiguration`.`product_version_id`
                    AND `pickwareProduct`.`default_supplier_id` = `productDefaultSupplierConfiguration`.`supplier_id`

                SET `purchaseListItem`.`product_supplier_configuration_id` = `productDefaultSupplierConfiguration`.`id`

                WHERE `purchaseListItem`.`product_supplier_configuration_id` IS NULL
                    AND `purchaseListItem`.`product_id` IN (:productIds)
                    AND `purchaseListItem`.`product_version_id` = :liveVersionId
                SQL,
            [
                'productIds' => array_map('hex2bin', $productIds),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            ['productIds' => ArrayParameterType::STRING],
        );
    }
}
