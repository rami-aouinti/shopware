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
use Pickware\PickwareErpStarter\PurchaseList\Model\PurchaseListItemCollection;
use Pickware\PickwareErpStarter\PurchaseList\Model\PurchaseListItemDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

class PurchaseListService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Connection $connection,
    ) {}

    public function clearPurchaseList(): void
    {
        $this->connection->executeStatement('DELETE FROM `pickware_erp_purchase_list_item`');
    }

    public function hasPurchaseListItemsWithoutSupplier(Context $context): bool
    {
        /** @var PurchaseListItemCollection $purchaseListItemWithoutSupplier */
        $purchaseListItemWithoutSupplier = $this->entityManager->findBy(
            PurchaseListItemDefinition::class,
            ['productSupplierConfigurationId' => null],
            $context,
        );

        return $purchaseListItemWithoutSupplier->count() !== 0;
    }

    public function getFilteredPurchasePriceTotalNet(Criteria $criteria, Context $context): float
    {
        $whereCondition = '';
        $parameters = ['defaultCurrencyNetPriceJsonPath' => sprintf('$.c%s.net', Defaults::CURRENCY)];
        $parameterConfiguration = [];
        if (count($criteria->getFilters()) > 0) {
            /** @var string[] $purchaseListItemIds */
            $purchaseListItemIds = $this->entityManager->findIdsBy(PurchaseListItemDefinition::class, $criteria, $context);

            $whereCondition = 'WHERE pli.`id` IN (:purchaseListItemIds)';
            $parameters['purchaseListItemIds'] = array_map(
                fn(string $id): string => Uuid::fromHexToBytes($id),
                $purchaseListItemIds,
            );
            $parameterConfiguration['purchaseListItemIds'] = ArrayParameterType::BINARY;
        }

        /** @var string|null $result */
        $result = $this->connection->fetchOne(
            <<<SQL
                SELECT
                    SUM(
                        IFNULL(pli.`quantity`, 0) *
                        IF(
                          psc.`id` IS NULL,
                          0,
                          IFNULL(JSON_UNQUOTE(JSON_EXTRACT(psc.`purchase_prices`, :defaultCurrencyNetPriceJsonPath)), 0)
                        )
                    ) AS `purchasePriceTotalNet`
                FROM `pickware_erp_purchase_list_item` pli
                LEFT JOIN `pickware_erp_product_supplier_configuration` psc ON pli.`product_supplier_configuration_id` = psc.`id`
                {$whereCondition}
                SQL,
            $parameters,
            $parameterConfiguration,
        );

        return (float) $result ?: 0.0;
    }

    /**
     * @param string[] $productIds
     *
     * @return array<string, string>
     */
    public function getSupplierConfigurationForProductsWithStrategy(array $productIds, PurchaseListSupplierConfigurationAssignmentStrategy $strategy): array
    {
        /** @var array<string, string> $result */
        $result = match ($strategy) {
            PurchaseListSupplierConfigurationAssignmentStrategy::Fastest => $this->connection->fetchAllKeyValue(
                <<<SQL
                    SELECT LOWER(HEX(`newAssignment`.`product_id`)), LOWER(HEX(`newAssignment`.`id`))
                    FROM (
                        SELECT `supplierConfiguration`.*,
                               ROW_NUMBER() OVER (
                                   PARTITION BY `product_id`
                                   -- If both delivery times are NULL, we coalesce to 4294967296 to make sure such a configuration
                                   -- is ranked last and not first, since we're sorting in ascending order.
                                   ORDER BY COALESCE(`delivery_time_days`, `supplier`.`default_delivery_time`, 4294967296) ASC, `supplier_is_default` DESC
                               ) AS `rowNumber`
                        FROM `pickware_erp_product_supplier_configuration` `supplierConfiguration`
                        INNER JOIN `pickware_erp_supplier` `supplier`
                            ON `supplierConfiguration`.`supplier_id` = `supplier`.`id`
                    ) `newAssignment`
                    WHERE `newAssignment`.`rowNumber` = 1 AND `newAssignment`.`product_id` IN (:productIds)
                    SQL,
                ['productIds' => Uuid::fromHexToBytesList($productIds)],
                ['productIds' => ArrayParameterType::BINARY],
            ),
            PurchaseListSupplierConfigurationAssignmentStrategy::Cheapest => $this->connection->fetchAllKeyValue(
                <<<SQL
                    SELECT LOWER(HEX(`newAssignment`.`product_id`)), LOWER(HEX(`newAssignment`.`id`))
                    FROM (
                        SELECT *,
                               ROW_NUMBER() OVER (
                                   PARTITION BY `product_id`
                                   ORDER BY JSON_EXTRACT(`purchase_prices`, :netPurchasePricePath) ASC, `supplier_is_default` DESC
                               ) AS `rowNumber`
                        FROM `pickware_erp_product_supplier_configuration`
                    ) `newAssignment`
                    WHERE `newAssignment`.`rowNumber` = 1 AND `newAssignment`.`product_id` IN (:productIds)
                    SQL,
                [
                    'productIds' => Uuid::fromHexToBytesList($productIds),
                    'netPurchasePricePath' => '$.c' . Defaults::CURRENCY . '.net',
                ],
                ['productIds' => ArrayParameterType::BINARY],
            ),
            // phpcs:ignore VIISON.Arrays.ArrayDeclaration.IndexNoNewline -- false positive
            PurchaseListSupplierConfigurationAssignmentStrategy::Default => $this->connection->fetchAllKeyValue(
                <<<SQL
                    SELECT LOWER(HEX(`product_id`)), LOWER(HEX(`id`))
                    FROM `pickware_erp_product_supplier_configuration`
                    WHERE `supplier_is_default` = TRUE
                        AND `product_id` IN (:productIds)
                    SQL,
                ['productIds' => Uuid::fromHexToBytesList($productIds)],
                ['productIds' => ArrayParameterType::BINARY],
            ),
            // phpcs:ignore VIISON.Arrays.ArrayDeclaration.IndexNoNewline -- false positive
        };

        return $result;
    }
}
