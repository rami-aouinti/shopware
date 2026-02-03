<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Config;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareWms\DocumentPrintingConfig\Model\DocumentPrintingConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Shipping\ShippingEvents;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ShippingMethodDocumentPrintingConfigSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly EntityManager $entityManager) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ShippingEvents::SHIPPING_METHOD_WRITTEN_EVENT => 'onShippingMethodWritten',
        ];
    }

    public function onShippingMethodWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $event->getContext()->scope(Context::SYSTEM_SCOPE, function($context) use ($event): void {
            $shippingMethodIds = $this->getInsertedEntityIds($event);
            if (count($shippingMethodIds) === 0) {
                return;
            }
            $this->createDocumentPrintingConfigs($shippingMethodIds, $context);
        });
    }

    private function getInsertedEntityIds(EntityWrittenEvent $entityWrittenEvent): array
    {
        $entityIds = [];
        foreach ($entityWrittenEvent->getWriteResults() as $writeResult) {
            if ($writeResult->getOperation() === EntityWriteResult::OPERATION_INSERT) {
                $entityIds[] = $writeResult->getPayload()['id'];
            }
        }

        return $entityIds;
    }

    private function createDocumentPrintingConfigs(array $shippingMethodIds, Context $context): void
    {
        /** @var DocumentTypeEntity $deliveryNoteDocumentType */
        $deliveryNoteDocumentType = $this->entityManager->getOneBy(
            DocumentTypeDefinition::class,
            ['technicalName' => DeliveryNoteRenderer::TYPE],
            $context,
        );
        $documentPrintingConfigs = array_map(
            fn(string $shippingMethodId) => [
                'shippingMethodId' => $shippingMethodId,
                'documentTypeId' => $deliveryNoteDocumentType->getId(),
                'copies' => 1,
            ],
            $shippingMethodIds,
        );
        $this->entityManager->create(
            DocumentPrintingConfigDefinition::class,
            $documentPrintingConfigs,
            $context,
        );
    }
}
