<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Delivery;

use Exception;
use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\Batch\OrderDocument\OrderDocumentBatchInfoSubscriber;
use Pickware\PickwareErpStarter\InvoiceStack\InvoiceStack;
use Pickware\PickwareErpStarter\InvoiceStack\InvoiceStackService;
use Pickware\PickwareErpStarter\PickingProperty\OrderDocument\OrderDocumentGenerationSubscriber;
use Pickware\PickwareWms\Delivery\Model\DeliveryDefinition;
use Pickware\PickwareWms\Delivery\Model\DeliveryEntity;
use Pickware\PickwareWms\Delivery\Model\DeliveryOrderDocumentMappingDefinition;
use Pickware\PickwareWms\DeliveryNote\DeliveryNoteLineItemFilterer;
use Pickware\PickwareWms\DocumentPrintingConfig\Model\DocumentPrintingConfigEntity;
use Pickware\PickwareWms\PickingProcess\PickingProcessException;
use Pickware\PickwareWms\PickingProperty\OrderDocumentPickingPropertyProvider;
use Pickware\ShippingBundle\Shipment\ShipmentBlueprintCreationConfiguration;
use Pickware\ShippingBundle\Shipment\ShipmentService;
use Pickware\ShopwareExtensionsBundle\OrderDocument\OrderDocumentService;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Framework\Context;

class DeliveryDocumentService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly InvoiceStackService $invoiceStackService,
        private readonly OrderDocumentService $orderDocumentService,
        private readonly DeliveryShipmentCreation $deliveryShipmentCreation,
        private readonly ?ShipmentService $shipmentService,
        private readonly OrderDocumentPickingPropertyProvider $orderDocumentPickingPropertyProvider,
    ) {}

    public function appendInvoiceToDelivery(string $deliveryId, Context $context): void
    {
        /** @var DeliveryEntity $delivery */
        $delivery = $this->entityManager->getByPrimaryKey(
            DeliveryDefinition::class,
            $deliveryId,
            $context,
            ['order'],
        );

        $latestOpenInvoiceStack = $this->invoiceStackService
            ->getInvoiceStacksOfOrder($delivery->getOrderId(), $context)
            ->filter(fn(InvoiceStack $invoiceStack) => $invoiceStack->isOpen)
            ->getLatest();
        $documentIds = [];
        if ($latestOpenInvoiceStack !== null) {
            $documentIds = [$latestOpenInvoiceStack->invoice->id];
            foreach ($latestOpenInvoiceStack->invoiceCorrections as $invoiceCorrection) {
                $documentIds[] = $invoiceCorrection->id;
            }
        } else {
            $documentConfig = [];
            if (
                defined('Pickware\\PickwareErpStarter\\Batch\\OrderDocument\\OrderDocumentBatchInfoSubscriber::STOCK_CONTAINER_ID_CONFIG_KEY')
                && $delivery->getStockContainerId() !== null
            ) {
                $documentConfig[OrderDocumentBatchInfoSubscriber::STOCK_CONTAINER_ID_CONFIG_KEY] = $delivery->getStockContainerId();
            }
            if (defined('Pickware\\PickwareErpStarter\\PickingProperty\\OrderDocument\\OrderDocumentGenerationSubscriber::ADDITIONAL_ORDER_DOCUMENT_PICKING_PROPERTIES_RECORDS_KEY')) {
                // For the invoice document we add additional picking properties because the invoice document shows all
                // items. Not only what is currently picked via WMS.
                $wmsPickingProperties = $this->orderDocumentPickingPropertyProvider->getOrderDocumentPickingProperties(
                    $deliveryId,
                    $context,
                );
                $documentConfig[OrderDocumentGenerationSubscriber::ADDITIONAL_ORDER_DOCUMENT_PICKING_PROPERTIES_RECORDS_KEY] = $wmsPickingProperties;
            }

            try {
                $documentIds[] = $this->orderDocumentService->createDocumentWithTechnicalName(
                    $delivery->getOrderId(),
                    InvoiceRenderer::TYPE,
                    $context,
                    ['documentConfig' => $documentConfig],
                );
            } catch (Exception $e) {
                // During the document generation, all kind of Exception can appear, therefore we catch them all
                // here (instead of catching a specific exception class, as usually)
                throw PickingProcessException::creationOfInvoiceFailed(
                    $delivery->getOrder()->getId(),
                    $delivery->getOrder()->getOrderNumber(),
                    $e,
                );
            }
        }

        $documentMappingPayloads = array_map(
            fn(string $documentId) => [
                'deliveryId' => $deliveryId,
                'orderDocumentId' => $documentId,
            ],
            $documentIds,
        );
        $this->entityManager->create(
            DeliveryOrderDocumentMappingDefinition::class,
            $documentMappingPayloads,
            $context,
        );
    }

    public function appendDeliveryNoteToDelivery(string $deliveryId, Context $context): void
    {
        /** @var DeliveryEntity $delivery */
        $delivery = $this->entityManager->getByPrimaryKey(
            DeliveryDefinition::class,
            $deliveryId,
            $context,
            [
                'stockContainer.stocks',
                'order',
            ],
        );

        $productsInDelivery = [];
        if ($delivery->getStockContainer() !== null) {
            foreach ($delivery->getStockContainer()->getStocks() as $stock) {
                $productsInDelivery[$stock->getProductId()] = [
                    'productId' => $stock->getProductId(),
                    'quantity' => $stock->getQuantity(),
                ];
            }
        }

        $documentConfig = [
            DeliveryNoteLineItemFilterer::DOCUMENT_CONFIG_PRODUCTS_IN_DELIVERY_KEY => $productsInDelivery,
        ];
        if (
            defined('Pickware\\PickwareErpStarter\\Batch\\OrderDocument\\OrderDocumentBatchInfoSubscriber::STOCK_CONTAINER_ID_CONFIG_KEY')
            && $delivery->getStockContainerId() !== null
        ) {
            $documentConfig[OrderDocumentBatchInfoSubscriber::STOCK_CONTAINER_ID_CONFIG_KEY] = $delivery->getStockContainerId();
        }
        if (defined('Pickware\\PickwareErpStarter\\PickingProperty\\OrderDocument\\OrderDocumentGenerationSubscriber::OVERWRITE_ORDER_DOCUMENT_PICKING_PROPERTIES_RECORDS_KEY')) {
            // For the delivery note, we overwrite the picking properties because the document only shows items that are
            // currently picked via WMS.
            $wmsPickingProperties = $this->orderDocumentPickingPropertyProvider->getOrderDocumentPickingProperties(
                $deliveryId,
                $context,
            );
            $documentConfig[OrderDocumentGenerationSubscriber::OVERWRITE_ORDER_DOCUMENT_PICKING_PROPERTIES_RECORDS_KEY] = $wmsPickingProperties;
        }

        try {
            $deliveryNoteDocumentId = $this->orderDocumentService->createDocumentWithTechnicalName(
                $delivery->getOrderId(),
                DeliveryNoteRenderer::TYPE,
                $context,
                ['documentConfig' => $documentConfig],
            );
        } catch (Exception $exception) {
            // During the document generation, all kind of Exception can appear, therefore we catch them all
            // here (instead of catching a specific exception class, as usually)
            throw PickingProcessException::creationOfDeliveryNoteFailed(
                $delivery->getOrder()->getOrderNumber(),
                $delivery->getOrder()->getId(),
                $exception,
            );
        }

        $this->entityManager->create(
            DeliveryOrderDocumentMappingDefinition::class,
            [
                [
                    'deliveryId' => $deliveryId,
                    'orderDocumentId' => $deliveryNoteDocumentId,
                ],
            ],
            $context,
        );
    }

    /**
     * @param ImmutableCollection<DocumentPrintingConfigEntity> $documentPrintingConfigs
     */
    public function appendOtherDocumentsToDelivery(
        string $deliveryId,
        ImmutableCollection $documentPrintingConfigs,
        Context $context,
    ): void {
        /** @var DeliveryEntity $delivery */
        $delivery = $this->entityManager->getByPrimaryKey(
            DeliveryDefinition::class,
            $deliveryId,
            $context,
            [
                'order',
                'order.documents',
            ],
        );

        // Get all IDs of order documents which have the same document type as the document printing configs document
        // types
        $documentIds = $delivery
            ->getOrder()
            ->getDocuments()
            ->filter(
                fn(DocumentEntity $document) => $documentPrintingConfigs->containsElementSatisfying(
                    fn(DocumentPrintingConfigEntity $documentPrintingConfig) => $documentPrintingConfig->getDocumentTypeId() === $document->getDocumentTypeId(),
                ),
            )
            ->getIds();

        // We only create mappings for existing documents of other document types, because every document type can
        // require different parameters to create which we do not know.
        $documentMappingPayloads = array_map(
            fn(string $documentId) => [
                'deliveryId' => $deliveryId,
                'orderDocumentId' => $documentId,
            ],
            array_values($documentIds),
        );
        $this->entityManager->create(
            DeliveryOrderDocumentMappingDefinition::class,
            $documentMappingPayloads,
            $context,
        );
    }

    public function appendReturnLabelsToDelivery(string $deliveryId, string $shipmentId, Context $context): void
    {
        /** @var DeliveryEntity $delivery */
        $delivery = $this->entityManager->getByPrimaryKey(
            DeliveryDefinition::class,
            $deliveryId,
            $context,
            [
                'state',
            ],
        );

        $shipmentBlueprint = $this->shipmentService->createReturnShipmentBlueprintForOrder(
            $delivery->getOrderId(),
            ShipmentBlueprintCreationConfiguration::fromArray([
                'skipParcelRepacking' => true,
            ]),
            $context,
        );

        $this->deliveryShipmentCreation->createShipmentForDelivery(
            deliveryId: $deliveryId,
            shipmentId: $shipmentId,
            shipmentBlueprint: $shipmentBlueprint->shipmentBlueprint,
            isReturnShipment: true,
            context: $context,
        );
    }
}
