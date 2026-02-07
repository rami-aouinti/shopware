<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt;

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptType;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderEntity;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemEntity;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderException;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierDefinition;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\ShopwareExtensionsBundle\Context\ContextExtension;
use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\EntitySnapshotService;
use Pickware\ShopwareExtensionsBundle\Product\ProductNameFormatterService;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\System\User\UserEntity;

class GoodsReceiptCreationService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly EntitySnapshotService $entitySnapshotService,
        private readonly NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        private readonly InitialStateIdLoader $initialStateIdLoader,
        private readonly ProductNameFormatterService $productNameFormatterService,
    ) {}

    public function generateGoodsReceiptPayload(array $goodsReceiptPayload, Context $context): array
    {
        $number = $this->numberRangeValueGenerator->getValue(
            GoodsReceiptNumberRange::TECHNICAL_NAME,
            $context,
            salesChannelId: null,
        );
        $goodsReceiptPayload['number'] = $number;

        $userId = ContextExtension::findUserId($context);
        $goodsReceiptPayload['userId'] = $userId;
        if ($userId !== null) {
            /** @var UserEntity $user */
            $user = $this->entityManager->getByPrimaryKey(UserDefinition::class, $userId, $context);
            $goodsReceiptPayload['userSnapshot'] = [
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
            ];
        }
        unset($goodsReceiptPayload['user']);

        // The price will be calculated later
        $goodsReceiptPayload['price'] = null;

        if (isset($goodsReceiptPayload['warehouseId'])) {
            $warehouseSnapshots = $this->entitySnapshotService->generateSnapshots(
                WarehouseDefinition::class,
                [$goodsReceiptPayload['warehouseId']],
                $context,
            );
            $goodsReceiptPayload['warehouseSnapshot'] = $warehouseSnapshots[$goodsReceiptPayload['warehouseId']];
        }
        unset($goodsReceiptPayload['warehouse']);

        if (!isset($goodsReceiptPayload['stateId'])) {
            $initialGoodsReceiptStateId = $this->initialStateIdLoader->get(GoodsReceiptStateMachine::TECHNICAL_NAME);
            $goodsReceiptPayload['stateId'] = $initialGoodsReceiptStateId;
        }
        unset($goodsReceiptPayload['state']);

        $productIds = array_values(array_filter(array_column($goodsReceiptPayload['lineItems'], 'productId')));
        /** @var ProductCollection $products */
        $products = $this->entityManager->findBy(ProductDefinition::class, ['id' => $productIds], $context);
        $productNamesByProductId = $this->productNameFormatterService->getFormattedProductNames(
            $productIds,
            templateOptions: [],
            context: $context,
        );
        foreach ($goodsReceiptPayload['lineItems'] as &$lineItem) {
            unset($lineItem['product']);
            if ($lineItem['productId'] !== null) {
                $exception = new EntityNotFoundException('product', $lineItem['productId']);
                $lineItem['productSnapshot'] = [
                    'name' => $productNamesByProductId[$lineItem['productId']] ?? throw $exception,
                    'productNumber' => $products->get($lineItem['productId'])?->getProductNumber() ?? throw $exception,
                ];
            }
        }
        unset($lineItem);

        if (isset($goodsReceiptPayload['supplierId'])) {
            /** @var SupplierEntity $supplier */
            $supplier = $this->entityManager->getByPrimaryKey(
                SupplierDefinition::class,
                $goodsReceiptPayload['supplierId'],
                $context,
            );
            $goodsReceiptPayload['supplierSnapshot'] = [
                'name' => $supplier->getName(),
                'number' => $supplier->getNumber(),
            ];
            unset($goodsReceiptPayload['supplier']);
            $goodsReceiptPayload['type'] ??= GoodsReceiptType::Supplier;
        }

        if (isset($goodsReceiptPayload['customerId'])) {
            /** @var CustomerEntity $customer */
            $customer = $this->entityManager->getByPrimaryKey(
                CustomerDefinition::class,
                $goodsReceiptPayload['customerId'],
                $context,
            );
            $goodsReceiptPayload['customerSnapshot'] = [
                'firstName' => $customer->getFirstName(),
                'lastName' => $customer->getLastName(),
                'customerNumber' => $customer->getCustomerNumber(),
            ];
            unset($goodsReceiptPayload['customer']);
            $goodsReceiptPayload['type'] = GoodsReceiptType::Customer;
        }

        if (!isset($goodsReceiptPayload['supplierId']) && !isset($goodsReceiptPayload['customerId'])) {
            $goodsReceiptPayload['type'] ??= GoodsReceiptType::Free;
        }

        /** @var CurrencyEntity $currency */
        $currency = $this->entityManager->getByPrimaryKey(
            CurrencyDefinition::class,
            $goodsReceiptPayload['currencyId'] ?? Defaults::CURRENCY,
            $context,
        );
        $goodsReceiptPayload['currencyId'] ??= $currency->getId();
        $goodsReceiptPayload['totalRounding'] ??= $currency->getTotalRounding()->jsonSerialize();
        $goodsReceiptPayload['itemRounding'] ??= $currency->getItemRounding()->jsonSerialize();
        $goodsReceiptPayload['currencyFactor'] ??= $currency->getFactor();
        unset($goodsReceiptPayload['currency']);
        $goodsReceiptPayload['type'] ??= GoodsReceiptType::Free;

        return $goodsReceiptPayload;
    }

    public function createGoodsReceipt(array $goodsReceiptPayload, Context $context): void
    {
        $this->entityManager->create(
            GoodsReceiptDefinition::class,
            [$this->generateGoodsReceiptPayload($goodsReceiptPayload, $context)],
            $context,
        );
    }

    public function createGoodsReceiptPayloadsFromReturnOrder(array $returnOrderIds, Context $context): array
    {
        $goodsReceiptPayloads = [];
        foreach ($returnOrderIds as $returnOrderId) {
            /** @var ReturnOrderEntity $returnOrder */
            $returnOrder = $this->entityManager->findByPrimaryKey(
                ReturnOrderDefinition::class,
                $returnOrderId,
                $context,
                [
                    'lineItems',
                    'order.orderCustomer.customerId',
                ],
            );

            if (!$returnOrder) {
                throw ReturnOrderException::returnOrderNotFound([$returnOrderId], []);
            }

            if (!$returnOrder->getWarehouseId()) {
                throw new GoodsReceiptException(
                    GoodsReceiptError::missingWarehouse($returnOrderId, $returnOrder->getNumber()),
                );
            }

            $goodsReceiptPayloads[] = [
                'id' => Uuid::randomHex(),
                'type' => GoodsReceiptType::Customer,
                'warehouseId' => $returnOrder->getWarehouseId(),
                'returnOrders' => [['id' => $returnOrderId]],
                'customerId' => $returnOrder->getOrder()->getOrderCustomer()->getCustomerId(),
                'lineItems' => ImmutableCollection::create($returnOrder->getLineItems())
                    ->filter(fn(ReturnOrderLineItemEntity $lineItem) =>
                        $lineItem->getType() === ReturnOrderLineItemDefinition::TYPE_PRODUCT)
                    ->map(
                        fn(ReturnOrderLineItemEntity $returnOrderLineItem) => [
                            'id' => Uuid::randomHex(),
                            'productId' => $returnOrderLineItem->getProductId(),
                            'quantity' => $returnOrderLineItem->getQuantity(),
                            'returnOrderId' => $returnOrderId,
                        ],
                    )->asArray(),
            ];
        }

        return $goodsReceiptPayloads;
    }
}
