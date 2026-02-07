<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt\Subscriber;

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptEntity;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemEntity;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GoodsReceiptOriginStateUpdateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly GoodsReceiptOriginStateUpdateService $updateService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            GoodsReceiptDefinition::ENTITY_WRITTEN_EVENT => 'updateStateOfGoodsReceiptOriginsOnGoodsReceiptUpdate',
            GoodsReceiptLineItemDefinition::ENTITY_WRITTEN_EVENT => 'updateStateOfGoodsReceiptOriginsOnGoodsReceiptLineItemUpdate',
        ];
    }

    public function updateStateOfGoodsReceiptOriginsOnGoodsReceiptUpdate(EntityWrittenEvent $entityWrittenEvent): void
    {
        $context = $entityWrittenEvent->getContext();
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $goodsReceiptIds = [];
        foreach ($entityWrittenEvent->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();

            // The GoodsReceiptOriginStateUpdateService considers the state of goods receipts.
            // So when a goods receipt is updated we only want to recalculate the origin states if the goods receipt state changes.
            if (isset($payload['stateId'])) {
                $goodsReceiptIds[] = $payload['id'];
            }
        }

        if (count($goodsReceiptIds) === 0) {
            return;
        }

        $context->scope(
            Context::SYSTEM_SCOPE,
            function($systemScopeContext) use ($goodsReceiptIds): void {
                $goodsReceipts = $this->entityManager->findBy(
                    GoodsReceiptDefinition::class,
                    ['id' => $goodsReceiptIds],
                    $systemScopeContext,
                    [
                        'lineItems.goodsReceipt',
                    ],
                );
                $goodsReceiptLineItemIds = ImmutableCollection::create($goodsReceipts)
                    ->flatMap(
                        fn(GoodsReceiptEntity $goodsReceipt) => $goodsReceipt->getLineItems()->getElements(),
                    )
                    ->map(
                        fn(GoodsReceiptLineItemEntity $lineItem) => $lineItem->getId(),
                    )
                    ->asArray();
                $this->updateService->updateGoodsReceiptOriginState($goodsReceiptLineItemIds, $systemScopeContext);
            },
        );
    }

    public function updateStateOfGoodsReceiptOriginsOnGoodsReceiptLineItemUpdate(EntityWrittenEvent $entityWrittenEvent): void
    {
        $context = $entityWrittenEvent->getContext();

        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $goodsReceiptLineItemIds = [];
        foreach ($entityWrittenEvent->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();

            // The GoodsReceiptOriginStateUpdateService considers the quantity of goods receipt items.
            // So when a goods receipt line item is updated we only want to recalculate the origin states if the goods receipt line item quantity.
            // However, the attached origin may also change. In this case we also want to recalculate the origin state.
            if (self::isLineItemPayloadRelevantForStateUpdate($payload)) {
                $goodsReceiptLineItemIds[] = $payload['id'];
            }
        }

        if (count($goodsReceiptLineItemIds) === 0) {
            return;
        }

        $context->scope(
            Context::SYSTEM_SCOPE,
            function($systemScopeContext) use ($goodsReceiptLineItemIds): void {
                $this->updateService->updateGoodsReceiptOriginState($goodsReceiptLineItemIds, $systemScopeContext);
            },
        );
    }

    private static function isLineItemPayloadRelevantForStateUpdate(array $payload): bool
    {
        if (isset($payload['quantity'])) {
            return true;
        }

        foreach (GoodsReceiptType::cases() as $goodsReceiptType) {
            $propertyName = $goodsReceiptType->getGoodsReceiptLineItemOriginPropertyName() . 'Id';
            if (isset($payload[$propertyName])) {
                return true;
            }
        }

        return false;
    }
}
