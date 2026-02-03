<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder\Model\Subscriber;

use DateInterval;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationCollection;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationEntity;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderCollection;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderDefinition;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderEntity;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderLineItemDefinition;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderLineItemEntity;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderStateMachine;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DeliveryDateUpdateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManager $entityManager,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            StateMachineTransitionEvent::class => 'setExpectedDeliveryDateForSupplierOrdersOnStateChange',
            SupplierOrderLineItemDefinition::ENTITY_WRITTEN_EVENT => 'setActualDeliveryDateForSupplierOrderOnLineItemWritten',
        ];
    }

    public function setExpectedDeliveryDateForSupplierOrdersOnStateChange(StateMachineTransitionEvent $event): void
    {
        if ($event->getEntityName() !== SupplierOrderDefinition::ENTITY_NAME) {
            return;
        }

        if ($event->getToPlace()->getTechnicalName() !== SupplierOrderStateMachine::STATE_CONFIRMED) {
            return;
        }

        $event->getContext()->scope(Context::SYSTEM_SCOPE, function(Context $context) use ($event): void {
            $this->setExpectedDeliveryDateForConfirmedOrder($event->getEntityId(), $context);
        });
    }

    public function setActualDeliveryDateForSupplierOrderOnLineItemWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $lineItemIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();
            if (!array_key_exists('actualDeliveryDate', $payload)) {
                continue;
            }
            $lineItemIds[] = $payload['id'];
        }

        if (count($lineItemIds) === 0) {
            return;
        }

        $supplierOrderIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(`supplier_order_id`))
            FROM `pickware_erp_supplier_order_line_item`
            WHERE `id` IN (:lineItemIds)',
            ['lineItemIds' => array_map('hex2bin', $lineItemIds)],
            ['lineItemIds' => ArrayParameterType::BINARY],
        );

        if (count($supplierOrderIds) === 0) {
            return;
        }

        $event->getContext()->scope(Context::SYSTEM_SCOPE, function(Context $context) use ($supplierOrderIds): void {
            $this->updateActualDeliveryDateForSupplierOrders($supplierOrderIds, $context);
        });
    }

    private function setExpectedDeliveryDateForConfirmedOrder(string $entityId, Context $context): void
    {
        $supplierOrder = $this->getSupplierOrderEntity($entityId, $context);
        $supplierDefaultDeliveryTime = $supplierOrder->getSupplier()->getDefaultDeliveryTime();

        $supplierOrderLineItemUpdatePayload = ImmutableCollection::create($supplierOrder->getLineItems())
            ->filter(fn(SupplierOrderLineItemEntity $lineItem) => !$lineItem->getExpectedDeliveryDate())
            ->map(function(SupplierOrderLineItemEntity $lineItem) use ($supplierDefaultDeliveryTime, $supplierOrder): array {
                /** @var ?ProductSupplierConfigurationCollection $productSupplierConfigurations */
                $productSupplierConfigurations = $lineItem
                    ->getProduct()
                    ?->getExtension('pickwareErpProductSupplierConfigurations');
                $productDefaultDeliveryTime = $productSupplierConfigurations
                    ?->firstWhere(fn(ProductSupplierConfigurationEntity $productSupplierConfiguration) => $productSupplierConfiguration->getSupplierId() === $supplierOrder->getSupplierId())
                    ?->getDeliveryTimeDays();
                $timeToAdd = $productDefaultDeliveryTime ?? $supplierDefaultDeliveryTime;

                return [
                    'id' => $lineItem->getId(),
                    'expectedDeliveryDate' => $timeToAdd ? (new DateTime())->add(new DateInterval(sprintf('P%sD', $timeToAdd))) : null,
                ];
            })
            ->filter(fn(array $updatePayload) => $updatePayload['expectedDeliveryDate'] !== null)
            ->asArray();

        if ($supplierOrder->getExpectedDeliveryDate() === null) {
            // Set the expected delivery date of the supplier order to the latest expected delivery date of its line items
            $latestExpectedDeliveryDate = ImmutableCollection::create(array_column($supplierOrderLineItemUpdatePayload, 'expectedDeliveryDate'))
                ->merge(ImmutableCollection::create($supplierOrder->getLineItems()->map(fn(SupplierOrderLineItemEntity $lineItem) => $lineItem->getExpectedDeliveryDate())))
                ->filter(fn(?DateTimeInterface $date) => $date !== null)
                ->sorted(fn(DateTimeInterface $a, DateTimeInterface $b) => -($a->getTimestamp() <=> $b->getTimestamp()))
                ->first();

            $this->entityManager->update(
                SupplierOrderDefinition::class,
                [
                    [
                        'id' => $entityId,
                        'expectedDeliveryDate' => $latestExpectedDeliveryDate,
                    ],
                ],
                $context,
            );
        }

        $this->entityManager->update(
            SupplierOrderLineItemDefinition::class,
            $supplierOrderLineItemUpdatePayload,
            $context,
        );
    }

    /**
     * Sets the actual delivery date for the given supplier orders each IF all supplier order line items have an
     * actual delivery set THEN sets the latest of these dates as the actual delivery date for the supplier order.
     *
     * @param string[] $supplierOrderIds
     */
    private function updateActualDeliveryDateForSupplierOrders(array $supplierOrderIds, Context $context): void
    {
        /** @var SupplierOrderCollection $supplierOrders */
        $supplierOrders = $this->entityManager->findBy(
            SupplierOrderDefinition::class,
            [
                'id' => $supplierOrderIds,
                'actualDeliveryDate' => null,
            ],
            $context,
            ['lineItems'],
        );

        $supplierOrderUpdatePayloads = [];
        foreach ($supplierOrders as $supplierOrder) {
            $allLineItemsHaveActualDeliveryDate = $supplierOrder->getLineItems()->reduce(
                fn(bool $allPreviousHaveDate, SupplierOrderLineItemEntity $lineItem) => $allPreviousHaveDate && $lineItem->getActualDeliveryDate() !== null,
                true,
            );
            if (!$allLineItemsHaveActualDeliveryDate) {
                continue;
            }

            $latestActualDeliveryDate = $supplierOrder->getLineItems()->reduce(
                fn(?DateTimeInterface $latestDateSoFar, SupplierOrderLineItemEntity $lineItem) => $latestDateSoFar === null || $lineItem->getActualDeliveryDate() > $latestDateSoFar ? $lineItem->getActualDeliveryDate() : $latestDateSoFar,
                null,
            );

            $supplierOrderUpdatePayloads[] = [
                'id' => $supplierOrder->getId(),
                'actualDeliveryDate' => $latestActualDeliveryDate,
            ];
        }

        $this->entityManager->update(
            SupplierOrderDefinition::class,
            $supplierOrderUpdatePayloads,
            $context,
        );
    }

    private function getSupplierOrderEntity(string $entityId, Context $context): SupplierOrderEntity
    {
        /** @var SupplierOrderEntity $supplierOrder */
        $supplierOrder = $this->entityManager->getByPrimaryKey(
            SupplierOrderDefinition::class,
            $entityId,
            $context,
            [
                'supplier',
                'lineItems.product.pickwareErpProductSupplierConfigurations',
            ],
        );

        return $supplierOrder;
    }
}
