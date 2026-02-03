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
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PickwareErpStarter\Batch\BatchException;
use Pickware\PickwareErpStarter\Batch\BatchManagementDevFeatureFlag;
use Pickware\PickwareErpStarter\Batch\BatchManagementProdFeatureFlag;
use Pickware\PickwareErpStarter\Batch\BatchStockAssignmentService;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemEntity;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Context;

/**
 * @phpstan-type GoodsReceiptLineItemUpdate array{
 *      batchId?: string|null,
 *      destinationAssignmentSource?: string,
 *      destinationBinLocationId?: string|null,
 *      price?: float,
 * }
 */
class GoodsReceiptUpdateService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly BatchStockAssignmentService $batchStockAssignmentService,
        private readonly GoodsReceiptPriceCalculationService $goodsReceiptPriceCalculationService,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    /**
     * @param GoodsReceiptLineItemUpdate $payload
     */
    public function updateGoodsReceiptLineItem(string $lineItemId, array $payload, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(function() use ($lineItemId, $payload, $context): void {
            /** @var GoodsReceiptLineItemEntity $lineItem */
            $lineItem = $this->entityManager->getByPrimaryKey(
                GoodsReceiptLineItemDefinition::class,
                $lineItemId,
                $context,
                [
                    'goodsReceipt.state',
                    'product.tax.rules',
                ],
            );

            if (
                array_key_exists('batchId', $payload)
                && $this->featureFlagService->isActive(BatchManagementDevFeatureFlag::NAME)
                && $this->featureFlagService->isActive(BatchManagementProdFeatureFlag::NAME)
            ) {
                $allowedGoodsReceiptStates = [
                    GoodsReceiptStateMachine::STATE_CREATED,
                    GoodsReceiptStateMachine::STATE_APPROVED,
                ];
                $goodsReceiptStateName = $lineItem->getGoodsReceipt()->getState()->getTechnicalName();
                if (!in_array($goodsReceiptStateName, $allowedGoodsReceiptStates, true)) {
                    throw new GoodsReceiptException(GoodsReceiptError::invalidGoodsReceiptStateForAction(
                        goodsReceiptId: $lineItem->getGoodsReceiptId(),
                        actualStateName: $lineItem->getGoodsReceipt()->getState()->getTechnicalName(),
                        expectedStateNames: $allowedGoodsReceiptStates,
                    ));
                }
                if ($goodsReceiptStateName === GoodsReceiptStateMachine::STATE_APPROVED) {
                    if ($payload['batchId'] === null) {
                        throw new GoodsReceiptException(GoodsReceiptError::invalidGoodsReceiptStateForAction(
                            goodsReceiptId: $lineItem->getGoodsReceiptId(),
                            actualStateName: $lineItem->getGoodsReceipt()->getState()->getTechnicalName(),
                            expectedStateNames: [GoodsReceiptStateMachine::STATE_CREATED],
                        ));
                    }

                    try {
                        $this->batchStockAssignmentService->changeStockBatchAssignment(
                            productId: $lineItem->getProductId(),
                            stockLocation: StockLocationReference::goodsReceipt($lineItem->getGoodsReceiptId()),
                            currentBatchId: $lineItem->getBatchId(),
                            newBatchId: $payload['batchId'],
                            quantityChangeAmount: $lineItem->getQuantity(),
                            context: $context,
                        );
                    } catch (BatchException $exception) {
                        throw new GoodsReceiptException($exception->serializeToJsonApiErrors());
                    }
                }
            }

            $recalculatePricesAfterUpdate = false;
            if (array_key_exists('price', $payload)) {
                $taxRules = new TaxRuleCollection();
                $productTaxRate = $lineItem->getProduct()?->getTax()?->getTaxRate();
                if ($productTaxRate !== null) {
                    $taxRules->add(new TaxRule($productTaxRate));
                }
                $payload['priceDefinition'] = new QuantityPriceDefinition(
                    price: $payload['price'],
                    taxRules: $taxRules,
                    quantity: $lineItem->getQuantity(),
                );
                unset($payload['price']);
                $recalculatePricesAfterUpdate = true;
            }

            $payload['id'] = $lineItemId;
            $this->entityManager->update(
                GoodsReceiptLineItemDefinition::class,
                [$payload],
                $context,
            );

            if ($recalculatePricesAfterUpdate) {
                $this->goodsReceiptPriceCalculationService->recalculateGoodsReceipts([$lineItem->getGoodsReceiptId()], $context);
            }
        });
    }
}
