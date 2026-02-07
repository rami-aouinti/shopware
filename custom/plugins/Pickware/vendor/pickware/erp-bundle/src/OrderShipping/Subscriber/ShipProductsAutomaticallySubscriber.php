<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderShipping\Subscriber;

use Pickware\DalBundle\EntityManager;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\OrderShipping\OrderParcelService;
use Pickware\PickwareErpStarter\OrderShipping\ShipProductsAutomaticallyMessage;
use Pickware\PickwareErpStarter\OrderShipping\ShipProductsAutomaticallyProdFeatureFlag;
use Pickware\PickwareErpStarter\Picking\OrderQuantitiesToShipCalculator;
use Pickware\PickwareErpStarter\Picking\PickingRequest;
use Pickware\PickwareErpStarter\Picking\PickingStrategyStockShortageException;
use Pickware\PickwareErpStarter\Picking\ProductOrthogonalPickingStrategy;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareErpStarter\StockMovementProcess\AutomaticallyShippedStockMovementProcessType;
use Pickware\PickwareErpStarter\StockMovementProcess\Model\StockMovementProcess;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(handles: ShipProductsAutomaticallyMessage::class)]
class ShipProductsAutomaticallySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly OrderQuantitiesToShipCalculator $orderQuantitiesToShipCalculator,
        private readonly ProductOrthogonalPickingStrategy $pickingStrategy,
        #[Autowire(service: 'messenger.default_bus')]
        private readonly MessageBusInterface $messageBus,
        private readonly FeatureFlagService $featureFlagService,
        private readonly OrderParcelService $orderParcelService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            OrderEvents::ORDER_LINE_ITEM_WRITTEN_EVENT => 'dispatchShipProductsAutomaticallyMessage',
        ];
    }

    public function dispatchShipProductsAutomaticallyMessage(EntityWrittenEvent $event): void
    {
        if (!$this->shouldProductsBeShippedAutomatically($event->getContext())) {
            return;
        }

        $orderLineItemIds = ImmutableCollection::create($event->getWriteResults())
            ->map(fn(EntityWriteResult $writeResult) => $writeResult->getPrimaryKey())
            ->deduplicate();
        $orderIds = $this->entityManager->findBy(
            OrderLineItemDefinition::class,
            ['id' => $orderLineItemIds->asArray()],
            $event->getContext(),
        )->map(fn(OrderLineItemEntity $orderLineItem) => $orderLineItem->getOrderId());

        $this->messageBus->dispatch(new ShipProductsAutomaticallyMessage(
            array_values(array_unique($orderIds)),
            $event->getContext(),
        ));
    }

    private function shouldProductsBeShippedAutomatically(Context $context): bool
    {
        return $context->getVersionId() === Defaults::LIVE_VERSION
            && $this->featureFlagService->isActive(ShipProductsAutomaticallyProdFeatureFlag::NAME);
    }

    public function __invoke(ShipProductsAutomaticallyMessage $message): void
    {
        $orders = $this->entityManager->findBy(
            OrderDefinition::class,
            ['id' => $message->getOrderIds()],
            $message->getContext(),
            ['lineItems.product.pickwareErpPickwareProduct'],
        );
        $productQuantitiesToShipByOrderId = $this->orderQuantitiesToShipCalculator->calculateProductsToShipForOrdersExcludingExternalFulfillments(
            orderIds: $message->getOrderIds(),
            context: $message->getContext(),
        );

        foreach ($productQuantitiesToShipByOrderId as $orderId => $productQuantitiesToShip) {
            /** @var OrderEntity $order */
            $order = $orders->get($orderId);
            $productQuantitiesForAutomaticallyShippedProducts = $productQuantitiesToShip
                ->filter(function(ProductQuantity $productQuantityToShip) use ($order) {
                    // In case the order line items are modified between the order fetch and the calculation of the
                    // products to ship, the order line item might not be present in the fetched order, hence the
                    // nullability of the order line item which we filter out in that case. This is not a problem,
                    // because the quantity to ship will correct itself in the next run of the message handler.
                    /** @var ?OrderLineItemEntity $orderLineItem */
                    $orderLineItem = $order
                        ->getLineItems()
                        ?->firstWhere(
                            fn(OrderLineItemEntity $orderLineItem) => $orderLineItem->getProductId() === $productQuantityToShip->getProductId(),
                        );

                    return $orderLineItem?->getProduct()?->getExtension('pickwareErpPickwareProduct')?->getShipAutomatically() === true;
                });
            try {
                $stockToShipAutomatically = $this->pickingStrategy->calculatePickingSolution(
                    pickingRequest: new PickingRequest(
                        productQuantities: $productQuantitiesForAutomaticallyShippedProducts,
                        sourceStockArea: StockArea::everywhere(),
                    ),
                    context: $message->getContext(),
                );
            } catch (PickingStrategyStockShortageException $exception) {
                $stockToShipAutomatically = $exception->getPartialPickingRequestSolution();
            }

            if ($stockToShipAutomatically->count() > 0) {
                $this->orderParcelService->shipParcelForOrder(
                    stockToShip: $stockToShipAutomatically,
                    orderId: $orderId,
                    trackingCodes: [],
                    context: $message->getContext(),
                    stockMovementProcess: new StockMovementProcess(
                        type: new AutomaticallyShippedStockMovementProcessType(),
                        referencedEntityId: $orderId,
                        userId: $message->getContext()->getSource() instanceof AdminApiSource ? $message->getContext()->getSource()->getUserId() : null,
                    ),
                );
            }
        }
    }
}
