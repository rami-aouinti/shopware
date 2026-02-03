<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder;

use DateTime;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Config\Config;
use Pickware\PickwareErpStarter\PriceCalculation\OrderRecalculationService;
use Pickware\PickwareErpStarter\PurchaseList\Model\PurchaseListItemCollection;
use Pickware\PickwareErpStarter\PurchaseList\Model\PurchaseListItemDefinition;
use Pickware\PickwareErpStarter\PurchaseList\Model\PurchaseListItemEntity;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierDefinition;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierEntity;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseSnapshotGenerator;
use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\EntitySnapshotService;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

/**
 * @phpstan-import-type WarehouseSnapshot from WarehouseSnapshotGenerator
 */
class SupplierOrderCreationService
{
    // We currently only support supplier order with tax status net
    private const SUPPLIER_ORDER_TAX_STATUS = CartPrice::TAX_STATE_NET;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly EntitySnapshotService $entitySnapshotService,
        private readonly Config $config,
        private readonly SupplierOrderLineItemCreationService $supplierOrderLineItemCreationService,
        private readonly NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        private readonly StateMachineRegistry $stateMachineRegistry,
        private readonly OrderRecalculationService $orderRecalculationService,
    ) {}

    /**
     * Creates supplier orders from purchase list items that fit the given criteria. Note that the criteria is applied
     * to the PurchaseListItemDefinition repository.
     *
     * @return string[]
     */
    public function createSupplierOrdersFromPurchaseListItemCriteria(Criteria $criteria, Context $context): array
    {
        /** @var CurrencyEntity $currency */
        $currency = $this->entityManager->getByPrimaryKey(CurrencyDefinition::class, Defaults::CURRENCY, $context);
        // Only create supplier orders when the product has a respective supplier assigned. Products without suppliers
        // are ignored and no supplier orders can be created for them.
        $criteria->addFilter(new NotFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('productSupplierConfigurationId', null),
        ]));
        $criteria->addAssociations([
            'productSupplierConfiguration.supplier.address',
        ]);

        $supplierOrderPayloadsBySupplierId = [];
        $purchaseListItemIdsToBeDeleted = [];

        $warehouseId = $this->config->getDefaultReceivingWarehouseId();
        $warehouseSnapshot = $this->entitySnapshotService->generateSnapshots(
            WarehouseDefinition::class,
            [$warehouseId],
            $context,
        )[$warehouseId];

        // Use pagination to fetch the purchase list items in batches to reduce memory usage
        $page = 1;
        $batchSize = 50;
        $criteria->setLimit($batchSize);

        while (true) {
            $criteria->setOffset(($page - 1) * $batchSize);
            $page += 1;

            /** @var PurchaseListItemCollection $purchaseListItems */
            $purchaseListItems = $context->enableInheritance(fn(Context $context) => $this->entityManager->findBy(
                PurchaseListItemDefinition::class,
                $criteria,
                $context,
            ));

            if ($purchaseListItems->count() === 0) {
                break;
            }

            foreach ($purchaseListItems as $purchaseListItem) {
                $supplier = $purchaseListItem->getProductSupplierConfiguration()->getSupplier();

                if (!array_key_exists($supplier->getId(), $supplierOrderPayloadsBySupplierId)) {
                    $supplierOrderPayloadsBySupplierId[$supplier->getId()] = $this->createSupplierOrderPayload(
                        $supplier,
                        $currency,
                        $warehouseId,
                        $warehouseSnapshot,
                        $context,
                    );
                }

                $purchaseListItemIdsToBeDeleted[] = $purchaseListItem->getId();
            }

            $supplierOrderLineItemPayloads = $this->supplierOrderLineItemCreationService->createSupplierOrderLineItemPayloads(
                array_map(
                    fn(PurchaseListItemEntity $purchaseListItem) => new SupplierOrderLineItemPayloadCreationInput(
                        $purchaseListItem->getProductSupplierConfigurationId(),
                        $purchaseListItem->getQuantity(),
                    ),
                    $purchaseListItems->getElements(),
                ),
                $context,
            );

            foreach ($supplierOrderLineItemPayloads->getSupplierIds() as $supplierId) {
                $supplierOrderPayloadsBySupplierId[$supplierId]['lineItems'] = array_merge(
                    $supplierOrderPayloadsBySupplierId[$supplierId]['lineItems'],
                    $supplierOrderLineItemPayloads->getPayloadsBySupplierId($supplierId),
                );
            }
        }

        $this->entityManager->create(
            SupplierOrderDefinition::class,
            array_values($supplierOrderPayloadsBySupplierId),
            $context,
        );
        $this->entityManager->delete(PurchaseListItemDefinition::class, $purchaseListItemIdsToBeDeleted, $context);

        $supplierOrderIds = array_column($supplierOrderPayloadsBySupplierId, 'id');
        $this->orderRecalculationService->recalculateSupplierOrders($supplierOrderIds, $context);

        return $supplierOrderIds;
    }

    /**
     * @param string[] $purchaseListItemIds
     * @return string[]
     */
    public function createSupplierOrdersFromPurchaseListItems(array $purchaseListItemIds, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $purchaseListItemIds));

        return $this->createSupplierOrdersFromPurchaseListItemCriteria($criteria, $context);
    }

    public function createSupplierOrder(string $supplierId, Context $context): string
    {
        /** @var SupplierEntity $supplier */
        $supplier = $this->entityManager->getByPrimaryKey(
            SupplierDefinition::class,
            $supplierId,
            $context,
            ['address'],
        );
        /** @var CurrencyEntity $currency */
        $currency = $this->entityManager->getByPrimaryKey(CurrencyDefinition::class, Defaults::CURRENCY, $context);

        $warehouseId = $this->config->getDefaultReceivingWarehouseId();
        $warehouseSnapshot = $this->entitySnapshotService->generateSnapshots(
            WarehouseDefinition::class,
            [$warehouseId],
            $context,
        )[$warehouseId];

        $supplierOrderPayload = $this->createSupplierOrderPayload(
            $supplier,
            $currency,
            $warehouseId,
            $warehouseSnapshot,
            $context,
        );
        $this->entityManager->create(
            SupplierOrderDefinition::class,
            [$supplierOrderPayload],
            $context,
        );

        return $supplierOrderPayload['id'];
    }

    /**
     * @param WarehouseSnapshot $warehouseSnapshot
     * @return array<string, mixed>
     */
    private function createSupplierOrderPayload(
        SupplierEntity $supplier,
        CurrencyEntity $currency,
        string $warehouseId,
        array $warehouseSnapshot,
        Context $context,
    ): array {
        $initialSupplierOrderState = $this->stateMachineRegistry->getStateMachine(
            SupplierOrderStateMachine::TECHNICAL_NAME,
            $context,
        )->getInitialState();
        $initialSupplierOrderPaymentState = $this->stateMachineRegistry->getStateMachine(
            SupplierOrderPaymentStateMachine::TECHNICAL_NAME,
            $context,
        )->getInitialState();
        $number = $this->numberRangeValueGenerator->getValue(
            SupplierOrderNumberRange::TECHNICAL_NAME,
            $context,
            null,
        );

        return [
            'id' => Uuid::randomHex(),
            'supplierId' => $supplier->getId(),
            'supplierSnapshot' => [
                'name' => $supplier->getName(),
                'number' => $supplier->getNumber(),
                'email' => $supplier->getAddress() ? $supplier->getAddress()->getEmail() : null,
                'phone' => $supplier->getAddress() ? $supplier->getAddress()->getPhone() : null,
            ],
            'warehouseId' => $warehouseId,
            'warehouseSnapshot' => $warehouseSnapshot,
            'currencyId' => $currency->getId(),
            'itemRounding' => $currency->getItemRounding()->getVars(),
            'totalRounding' => $currency->getTotalRounding()->getVars(),
            'stateId' => $initialSupplierOrderState->getId(),
            'paymentStateId' => $initialSupplierOrderPaymentState->getId(),
            'number' => $number,
            'orderDateTime' => new DateTime(),
            'lineItems' => [],
            // The actual price will be calculated by the order recalculation service based on its order line items
            'price' => new CartPrice(
                0,
                0,
                0,
                new CalculatedTaxCollection(),
                new TaxRuleCollection(),
                self::SUPPLIER_ORDER_TAX_STATUS,
            ),
        ];
    }
}
