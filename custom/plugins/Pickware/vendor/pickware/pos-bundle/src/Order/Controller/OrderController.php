<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Order\Controller;

use Pickware\ApiVersioningBundle\Attributes\ApiLayer;
use Pickware\DalBundle\CriteriaFactory;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\TechnicalNameToIdConverter;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\ResponseFactory;
use Pickware\PickwareErpStarter\PaperTrail\PaperTrailLoggingService;
use Pickware\PickwareErpStarter\PaperTrail\PaperTrailUriProvider;
use Pickware\PickwareErpStarter\PickingProperty\PickingPropertyRecord;
use Pickware\PickwareErpStarter\PickingProperty\PickingPropertyRecordValue;
use Pickware\PickwareErpStarter\PickingProperty\PickingPropertyService;
use Pickware\PickwareErpStarter\Stock\ProductReservedStockUpdater;
use Pickware\PickwarePos\ApiVersion\ApiVersion20230721\ProductVariantApiLayer as ApiVersion20230721ProductVariantApiLayer;
use Pickware\PickwarePos\Coupon\NetiNextEasyCouponAdapter;
use Pickware\PickwarePos\Order\PosOrderShippingService;
use Pickware\PickwarePos\PaperTrail\PosPaperTrailUri;
use Pickware\ShopwareExtensionsBundle\OrderDelivery\OrderDeliveryCollectionExtension;
use Pickware\ShopwareExtensionsBundle\OrderTransaction\OrderTransactionCollectionExtension;
use Pickware\ShopwareExtensionsBundle\StateTransitioning\StateTransitionService;
use function Pickware\ShopwareExtensionsBundle\VersionCheck\minimumShopwareVersion;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class OrderController
{
    public function __construct(
        private readonly NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        private readonly PosOrderShippingService $posOrderShippingService,
        private readonly EntityManager $entityManager,
        private readonly TechnicalNameToIdConverter $technicalNameIdConverter,
        private readonly CriteriaFactory $criteriaFactory,
        private readonly StateTransitionService $stateTransitionService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly NetiNextEasyCouponAdapter $netiNextEasyCouponAdapter,
        private readonly ProductReservedStockUpdater $productReservedStockUpdater,
        #[Autowire(service: 'Shopware\\Core\\System\\SalesChannel\\Context\\SalesChannelContextFactory')]
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private readonly PickingPropertyService $pickingPropertyService,
        // Not available in older ERP versions
        private readonly ?PaperTrailUriProvider $paperTrailUriProvider = null,
        private readonly ?PaperTrailLoggingService $paperTrailLoggingService = null,
    ) {}

    #[ApiLayer(ids: [ApiVersion20230721ProductVariantApiLayer::class])]
    #[Route(path: '/api/_action/pickware-pos/create-order', methods: ['POST'])]
    public function createOrderAction(Context $context, Request $request): Response
    {
        $order = $request->get('order');
        if (!$order) {
            return ResponseFactory::createParameterMissingResponse('order');
        }
        if (!isset($order['id'])) {
            return ResponseFactory::createIdMissingForIdempotentCreationResponse(OrderDefinition::ENTITY_NAME);
        }

        $warehouseId = $request->get('warehouseId');
        $orderId = $order['id'];
        $orderAssociations = $request->get('orderAssociations', []);

        $this->paperTrailUriProvider?->registerUri(PosPaperTrailUri::withProcess('create-pos-order'));
        $this->paperTrailLoggingService?->logPaperTrailEvent(
            'Create POS order',
            [
                'orderId' => $orderId,
                'warehouseId' => $warehouseId,
            ],
        );

        $orderSearchCriteria = $this->criteriaFactory->makeCriteriaForEntitiesIdentifiedByIdWithAssociations(
            OrderDefinition::class,
            [$orderId],
            $orderAssociations,
        );

        $desiredOrderStateTechnicalName = $request->get('updateOrderStateToTechnicalName');
        $desiredPrimaryOrderDeliveryStateTechnicalName = $request->get('updatePrimaryOrderDeliveryStateToTechnicalName');
        $desiredPrimaryOrderTransactionStateTechnicalName = $request->get('updatePrimaryOrderTransactionStateToTechnicalName');

        $this->technicalNameIdConverter->convertTechnicalNamesToIdsInOrderPayload($order);

        $pickingPropertyRecords = $this->makePickingPropertyRecordsFromArray($order['pickingProperties'] ?? []);

        $stockShortage = [];
        $this->entityManager->runInTransactionWithRetry(
            function() use (
                $orderId,
                $warehouseId,
                $context,
                $order,
                $desiredOrderStateTechnicalName,
                $desiredPrimaryOrderDeliveryStateTechnicalName,
                $desiredPrimaryOrderTransactionStateTechnicalName,
                $pickingPropertyRecords,
                &$stockShortage
            ): void {
                $existingOrder = $this->entityManager->findByPrimaryKey(OrderDefinition::class, $orderId, $context);
                if (!$existingOrder) {
                    // Match shopware behavior by generating a deep link code for the order. See CartTransformer.php
                    $order['deepLinkCode'] = Random::getBase64UrlString(32);

                    if (empty($order['orderNumber'])) {
                        $order['orderNumber'] = $this->numberRangeValueGenerator->getValue(
                            OrderDefinition::ENTITY_NAME,
                            $context,
                            $order['salesChannelId'],
                        );
                    }

                    $order['lineItems'] = array_map(
                        function($lineItem) {
                            $lineItem['id'] ??= Uuid::randomHex();

                            return $lineItem;
                        },
                        $order['lineItems'] ?? [],
                    );

                    // Ensure to mark this order as a POS order
                    $order['customFields'] = array_merge(
                        $order['customFields'] ?? [],
                        ['isPosOrder' => true],
                    );

                    $this->netiNextEasyCouponAdapter->prepareOrderPayloadForPurchasableCoupons($order, $context);

                    $this->deferReservedStockCalculation(function() use ($context, $order, $orderId): void {
                        $this->entityManager->create(OrderDefinition::class, [$order], $context);
                        $this->updatePromotionIds($order['lineItems'], $orderId, $context);
                    }, $context);

                    $this->pickingPropertyService->createPickingPropertyRecordsForOrder(
                        $orderId,
                        $pickingPropertyRecords,
                        $context,
                    );
                }

                // It is necessary to stop deferring here first to ensure that the CheckoutOrderPlacedEvent has
                // up-to-date available stock when it is dispatched. This is why we call the defer method again
                // with a new callback.

                $this->deferReservedStockCalculation(function() use (
                    $existingOrder,
                    $context,
                    $orderId,
                    $desiredPrimaryOrderTransactionStateTechnicalName,
                    $desiredPrimaryOrderDeliveryStateTechnicalName,
                    $desiredOrderStateTechnicalName,
                    $warehouseId,
                    &$stockShortage
                ): void {
                    if (!$existingOrder) {
                        $this->dispatchCheckoutOrderPlacedEvent($orderId, $context);
                    }

                    // Coupons might be created by other plugins after the `OrderPlacedEvent` was dispatched
                    $this->netiNextEasyCouponAdapter->addCreatedCouponsToOrderLineItems($orderId, $context);

                    if ($warehouseId !== null) {
                        $stockShortage = $this->posOrderShippingService->forceShipOrderCompletely(
                            $orderId,
                            $warehouseId,
                            $context,
                        );
                    }

                    $this->transitionOrderStates(
                        $orderId,
                        $context,
                        $desiredOrderStateTechnicalName,
                        $desiredPrimaryOrderDeliveryStateTechnicalName,
                        $desiredPrimaryOrderTransactionStateTechnicalName,
                    );
                }, $context);
            },
        );

        $order = $this->entityManager->findOneBy(OrderDefinition::class, $orderSearchCriteria, $context);

        $this->paperTrailUriProvider?->reset();

        return new JsonResponse([
            'stockShortage' => $stockShortage,
            'order' => $order,
        ], Response::HTTP_CREATED);
    }

    private function deferReservedStockCalculation(callable $callback, Context $context): void
    {
        if (method_exists($this->productReservedStockUpdater, 'deferReservedStockCalculation')) {
            $this->productReservedStockUpdater->deferReservedStockCalculation($callback, $context);
        } else {
            $callback();
        }
    }

    #[ApiLayer(ids: [ApiVersion20230721ProductVariantApiLayer::class])]
    #[Route(path: '/api/_action/pickware-pos/complete-order-pickup', methods: ['POST'])]
    public function completeOrderPickUpAction(Context $context, Request $request): Response
    {
        $order = $request->get('order');
        if (!$order) {
            return ResponseFactory::createParameterMissingResponse('order');
        }
        if (!isset($order['id'])) {
            return (new JsonApiError([
                'status' => (string) Response::HTTP_BAD_REQUEST,
                'title' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
                'detail' => 'No ID for the order to create was specified. Please pass an order ID to ensure ' .
                    'idempotency of this action.',
            ]))->toJsonApiErrorResponse();
        }
        $orderId = $order['id'];
        $warehouseId = $request->get('warehouseId');
        if (!$warehouseId || !Uuid::isValid($warehouseId)) {
            return ResponseFactory::createUuidParameterMissingResponse('warehouseId');
        }

        $this->paperTrailUriProvider?->registerUri(PosPaperTrailUri::withProcess('complete-order-pickup'));
        $this->paperTrailLoggingService?->logPaperTrailEvent(
            'Complete POS order pickup',
            [
                'orderId' => $orderId,
                'warehouseId' => $warehouseId,
            ],
        );

        $orderAssociations = $request->get('orderAssociations', []);
        $orderSearchCriteria = $this->criteriaFactory->makeCriteriaForEntitiesIdentifiedByIdWithAssociations(
            OrderDefinition::class,
            [$orderId],
            $orderAssociations,
        );

        $shouldDeleteExistingOrderTransactionsBeforeUpdate = $request->get(
            'shouldDeleteExistingOrderTransactionsBeforeUpdate',
            false,
        );
        $desiredOrderStateTechnicalName = $request->get('updateOrderStateToTechnicalName');
        $desiredPrimaryOrderDeliveryStateTechnicalName = $request->get('updatePrimaryOrderDeliveryStateToTechnicalName');
        if ($desiredPrimaryOrderDeliveryStateTechnicalName === null) {
            // This ensures backward compatibility so the app does not have to require a new plugin version.
            $desiredPrimaryOrderDeliveryStateTechnicalName = $request->get('updateFirstOrderDeliveryStateToTechnicalName');
        }
        $desiredPrimaryOrderTransactionStateTechnicalName = $request->get('updatePrimaryOrderTransactionStateToTechnicalName');
        if ($desiredPrimaryOrderTransactionStateTechnicalName === null) {
            // This ensures backward compatibility so the app does not have to require a new plugin version.
            $desiredPrimaryOrderTransactionStateTechnicalName = $request->get('updateFirstOrderTransactionStateToTechnicalName');
        }
        $pickingPropertyRecords = $this->makePickingPropertyRecordsFromArray($order['pickingProperties'] ?? []);

        $stockShortage = [];

        $this->entityManager->runInTransactionWithRetry(
            function() use (
                $order,
                $orderId,
                $warehouseId,
                $shouldDeleteExistingOrderTransactionsBeforeUpdate,
                $desiredOrderStateTechnicalName,
                $desiredPrimaryOrderDeliveryStateTechnicalName,
                $desiredPrimaryOrderTransactionStateTechnicalName,
                $context,
                &$stockShortage,
                $pickingPropertyRecords,
            ): void {
                // 1. If desired delete all existing order transactions
                if ($shouldDeleteExistingOrderTransactionsBeforeUpdate) {
                    $this->entityManager->deleteByCriteria(
                        OrderTransactionDefinition::class,
                        ['orderId' => $orderId],
                        $context,
                    );
                }

                // 2. Update the order with the supplied sparse serialized order and add picking properties
                $this->technicalNameIdConverter->convertTechnicalNamesToIdsInOrderPayload($order);
                $this->entityManager->update(OrderDefinition::class, [$order], $context);

                $this->pickingPropertyService->createPickingPropertyRecordsForOrder(
                    $orderId,
                    $pickingPropertyRecords,
                    $context,
                );

                // 3. Ship the order items
                $stockShortage = $this->posOrderShippingService->forceShipOrderCompletely(
                    $orderId,
                    $warehouseId,
                    $context,
                );

                // 4. Transition the order, order delivery and order transaction states if desired
                $this->transitionOrderStates(
                    $orderId,
                    $context,
                    $desiredOrderStateTechnicalName,
                    $desiredPrimaryOrderDeliveryStateTechnicalName,
                    $desiredPrimaryOrderTransactionStateTechnicalName,
                );
            },
        );

        $order = $this->entityManager->getOneBy(OrderDefinition::class, $orderSearchCriteria, $context);

        $this->paperTrailUriProvider?->reset();

        return new JsonResponse([
            'stockShortage' => $stockShortage,
            'order' => $order,
        ], Response::HTTP_OK);
    }

    /**
     * @param array{
     *  productId: string,
     *  productSnapshot?: array<string, mixed>,
     *  pickingPropertyRecords?: array<array{name: string, value: string}>
     * } $pickingProperties
     * @return array<PickingPropertyRecord>
     */
    private function makePickingPropertyRecordsFromArray(array $pickingProperties): array
    {
        return array_map(
            fn(array $pickingProperty) => new PickingPropertyRecord(
                $pickingProperty['productId'],
                $pickingProperty['productSnapshot'] ?? null,
                array_map(
                    fn(array $record) => new PickingPropertyRecordValue(
                        $record['name'],
                        $record['value'],
                    ),
                    ($pickingProperty['pickingPropertyRecords'] ?? []),
                ),
            ),
            $pickingProperties,
        );
    }

    private function transitionOrderStates(
        string $orderId,
        Context $context,
        ?string $desiredOrderStateTechnicalName,
        ?string $desiredPrimaryOrderDeliveryStateTechnicalName,
        ?string $desiredPrimaryOrderTransactionStateTechnicalName,
    ): void {
        /** @var OrderEntity $order */
        $order = $this->entityManager->getByPrimaryKey(
            OrderDefinition::class,
            $orderId,
            $context,
            [
                'transactions.stateMachineState',
                'deliveries.stateMachineState',
                'stateMachineState',
            ],
        );

        // Transition the order delivery state if desired
        if ($desiredPrimaryOrderDeliveryStateTechnicalName !== null && $order->getDeliveries() !== null) {
            $primaryOrderDelivery = OrderDeliveryCollectionExtension::primaryOrderDelivery($order->getDeliveries());
            if (
                $primaryOrderDelivery !== null
                && $primaryOrderDelivery->getStateMachineState()->getTechnicalName() !== $desiredPrimaryOrderDeliveryStateTechnicalName
            ) {
                $this->stateTransitionService->ensureOrderDeliveryState(
                    $primaryOrderDelivery->getId(),
                    $desiredPrimaryOrderDeliveryStateTechnicalName,
                    $context,
                );
            }
        }

        // Transition the order state if desired
        if (
            $desiredOrderStateTechnicalName !== null
            && $order->getStateMachineState()->getTechnicalName() !== $desiredOrderStateTechnicalName
        ) {
            $this->stateTransitionService->ensureOrderState(
                $orderId,
                $desiredOrderStateTechnicalName,
                $context,
            );
        }

        // Transition the order transaction state if desired
        $primaryOrderTransaction = OrderTransactionCollectionExtension::getPrimaryOrderTransaction($order->getTransactions());
        if (
            $desiredPrimaryOrderTransactionStateTechnicalName
            && $primaryOrderTransaction
            && $primaryOrderTransaction->getStateMachineState()->getTechnicalName() !== $desiredPrimaryOrderTransactionStateTechnicalName
        ) {
            $this->stateTransitionService->ensureOrderTransactionState(
                $primaryOrderTransaction->getId(),
                $desiredPrimaryOrderTransactionStateTechnicalName,
                $context,
            );
        }
    }

    /**
     * Dispatches the CheckoutOrderPlacedEvent with the associations that shopware uses when dispatching the same event
     */
    private function dispatchCheckoutOrderPlacedEvent(string $orderId, Context $context): void
    {
        // Add the associations that shopware adds when creating the CheckoutOrderPlacedEvent in
        // `Checkout/Cart/SalesChannel/CartOrderRoute.php:order`
        /** @var OrderEntity $order */
        $order = $this->entityManager->getByPrimaryKey(
            OrderDefinition::class,
            $orderId,
            $context,
            [
                'orderCustomer.customer',
                'orderCustomer.salutation',
                'deliveries.shippingMethod',
                'deliveries.shippingOrderAddress.country',
                'deliveries.shippingOrderAddress.countryState',
                'transactions.paymentMethod',
                'lineItems.cover',
                'currency',
                'addresses.country',
                'addresses.countryState',
                'transactions',
            ],
        );

        if (minimumShopwareVersion('6.7')) {
            $salesChannelContext = $this->salesChannelContextFactory->create(
                Uuid::randomHex(),
                $order->getSalesChannelId(),
            );

            // EasyCoupons OrderSubscriber::isProperCheckoutOrderPlacedEvent() requires the CheckoutOrderPlacedEvent to be
            // dispatched without a SalesChannelApiSource or contain 'checkout-order-route' as a state.
            // Since the sales channel context is created via a factory which always has a SalesChannelApiSource, we need to
            // add the 'checkout-order-route' state to the sales channel context to ensure that the check passes.
            $salesChannelContext->addState('checkout-order-route');

            $salesChannelContext->getContext()->scope(Context::SYSTEM_SCOPE, fn() => $this->eventDispatcher->dispatch(
                new CheckoutOrderPlacedEvent(
                    $salesChannelContext,
                    $order,
                ),
            ));
        } else {
            $orderPlacedEvent = new CheckoutOrderPlacedEvent(
                $context,
                $order,
                $order->getSalesChannelId(),
            );

            $context->scope(Context::SYSTEM_SCOPE, fn() => $this->eventDispatcher->dispatch($orderPlacedEvent));
        }
    }

    /**
     * We do not use the order checkout and cart processor, but create the order entity  directly with
     * the DAL and throw the CheckoutOrderPlacedEvent manually to ensure compatibility with Shopware's
     * Flow Builder (issue: https://github.com/pickware/shopware-plugins/issues/2552).
     *
     * The CheckoutOrderPlacedEvent also triggers the PromotionRedemptionUpdater which accesses the
     * `promotionId` property of the order line items. This property is write-protected and is only
     * set by the LineItemTransformer (which is not used here).
     * To fix this, we need to manually set the `promotionId` property, if necessary, and create the
     * order in a System scope to allow writing this write-protected property.
     * Also the type needs to be set to `promotion` again, because the PromotionRedemptionUpdater checks the type
     * in the payload. Setting the type to `promotion` also allows the PromotionIndividualCodeRedeemer to progress
     * which requires the orderId to be set to not crash the code.
     *
     * Further, references:
     * PromotionId Bug since SW 6.4.10.0 https://github.com/pickware/shopware-plugins/issues/2547
     * https://issues.shopware.com/issues/NEXT-21488
     *
     * @param array<array{id: string, type: string, payload: array{promotionId: string}}> $lineItems
     */
    private function updatePromotionIds(array $lineItems, string $orderId, Context $context): void
    {
        $lineItemUpdatePayload = array_values(array_filter(
            $lineItems,
            fn(array $lineItem) => $lineItem['type'] === LineItem::PROMOTION_LINE_ITEM_TYPE,
        ));
        $lineItemUpdatePayload = array_map(
            fn(array $lineItem) => [
                'id' => $lineItem['id'],
                'type' => LineItem::PROMOTION_LINE_ITEM_TYPE,
                'promotionId' => $lineItem['payload']['promotionId'],
                'orderId' => $orderId,
            ],
            $lineItemUpdatePayload,
        );

        $context->scope(
            Context::SYSTEM_SCOPE,
            fn(Context $systemContext) => $this->entityManager->update(
                OrderLineItemDefinition::class,
                $lineItemUpdatePayload,
                $context,
            ),
        );
    }
}
