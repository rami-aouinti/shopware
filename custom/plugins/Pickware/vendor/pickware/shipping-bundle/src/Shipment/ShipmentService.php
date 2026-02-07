<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Shipment;

use DateTime;
use LogicException;
use Pickware\DalBundle\EntityCollectionExtension;
use Pickware\DalBundle\EntityManager;
use Pickware\MoneyBundle\Currency;
use Pickware\MoneyBundle\MoneyValue;
use Pickware\ShippingBundle\AddressCorrecting\AddressCorrectingService;
use Pickware\ShippingBundle\Carrier\Capabilities\CashOnDeliveryCapability;
use Pickware\ShippingBundle\Carrier\Capabilities\MultiTrackingCapability;
use Pickware\ShippingBundle\Carrier\CarrierAdapterRegistry;
use Pickware\ShippingBundle\Carrier\Model\CarrierDefinition;
use Pickware\ShippingBundle\Carrier\Model\CarrierEntity;
use Pickware\ShippingBundle\Config\ConfigService;
use Pickware\ShippingBundle\Config\Model\ShippingMethodConfigEntity;
use Pickware\ShippingBundle\Config\ShipmentConfigurationService;
use Pickware\ShippingBundle\Notifications\NotificationService;
use Pickware\ShippingBundle\Parcel\Parcel;
use Pickware\ShippingBundle\ParcelHydration\MovementReferenceNumberCustomField;
use Pickware\ShippingBundle\ParcelHydration\ParcelHydrationConfiguration;
use Pickware\ShippingBundle\ParcelHydration\ParcelHydrator;
use Pickware\ShippingBundle\ParcelPacking\ParcelPacker;
use Pickware\ShippingBundle\Privacy\PrivacyService;
use Pickware\ShippingBundle\Privacy\RemovedFieldNode;
use Pickware\ShippingBundle\Privacy\RemovedFieldTree;
use Pickware\ShippingBundle\Shipment\Model\ShipmentCollection;
use Pickware\ShippingBundle\Shipment\Model\ShipmentDefinition;
use Pickware\ShippingBundle\Shipment\Model\ShipmentEntity;
use Pickware\ShippingBundle\Shipment\Model\TrackingCodeEntity;
use Pickware\ShopwareExtensionsBundle\OrderDelivery\OrderDeliveryCollectionExtension;
use Pickware\ShopwareExtensionsBundle\OrderTransaction\OrderTransactionCollectionExtension;
use Shopware\Core\Checkout\Document\DocumentEntity as ShopwareDocumentEntity;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;

class ShipmentService
{
    public function __construct(
        private readonly ConfigService $configService,
        private readonly EntityManager $entityManager,
        private readonly ParcelHydrator $parcelHydrator,
        private readonly CarrierAdapterRegistry $carrierAdapterRegistry,
        private readonly ParcelPacker $parcelPacker,
        private readonly AddressCorrectingService $addressCorrectingService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly NotificationService $notificationService,
        private readonly PrivacyService $privacyService,
        private readonly ShipmentTrackingCodeUpdater $shipmentTrackingCodeUpdater,
        private readonly ShipmentConfigurationService $shipmentConfigurationService,
    ) {}

    public function createShipmentBlueprintForOrder(
        string $orderId,
        ShipmentBlueprintCreationConfiguration $shipmentBlueprintCreationConfiguration,
        ?array $productsInParcel,
        Context $context,
    ): ShipmentBlueprintCreationResult {
        $shipmentBlueprintsWithOrderId = $this->createShipmentBlueprints(
            [$orderId => $shipmentBlueprintCreationConfiguration],
            isReturnShipmentBlueprint: false,
            productsInParcelByOrderId: [$orderId => $productsInParcel],
            context: $context,
        );

        return $shipmentBlueprintsWithOrderId[0];
    }

    /**
     * @return ShipmentBlueprintCreationResult[]
     */
    public function createShipmentBlueprintsForOrders(
        array $shipmentBlueprintCreationConfigurationByOrderId,
        array $productsInParcelByOrderId,
        Context $context,
    ): array {
        return $this->createShipmentBlueprints(
            $shipmentBlueprintCreationConfigurationByOrderId,
            isReturnShipmentBlueprint: false,
            productsInParcelByOrderId: $productsInParcelByOrderId,
            context: $context,
        );
    }

    public function createShipmentForOrder(
        ShipmentBlueprint $shipmentBlueprint,
        string $orderId,
        Context $context,
        array $shipmentPayload = [],
    ): ShipmentsOperationResultSet {
        $shipmentPayload['id'] ??= Uuid::randomHex();
        $shipmentsOperationResultSet = $this->createShipments(
            [
                array_merge(
                    $shipmentPayload,
                    [
                        'orders' => [
                            [
                                'id' => $orderId,
                            ],
                        ],
                        'shipmentBlueprint' => $shipmentBlueprint,
                    ],
                ),
            ],
            $context,
        );

        $shipmentId = $shipmentPayload['id'];
        if (!$shipmentsOperationResultSet->isAnyOperationResultSuccessful()) {
            $this->entityManager->delete(ShipmentDefinition::class, [$shipmentId], $context);

            return $shipmentsOperationResultSet;
        }

        $this->shipmentTrackingCodeUpdater->copyTrackingCodesOfShipmentsToOrders([$shipmentId], $context);

        if ($shipmentBlueprint->getShipmentConfig()['enclosedReturnLabel'] ?? false) {
            $this->shipmentTrackingCodeUpdater->copyReturnTrackingCodesOfShipmentsToOrders([$shipmentId], $context);
        }

        return $shipmentsOperationResultSet;
    }

    public function createShipmentsForOrders(
        array $shipmentPayloads,
        Context $context,
    ): array {
        foreach ($shipmentPayloads as &$shipmentPayload) {
            $shipmentPayload['id'] ??= Uuid::randomHex();
        }
        unset($shipmentPayload);
        $shipmentsOperationResultSet = $this->createShipments($shipmentPayloads, $context);
        $shipmentIds = array_column($shipmentPayloads, 'id');
        /** @var ShipmentCollection $shipments */
        $shipments = $this->entityManager->findBy(
            ShipmentDefinition::class,
            [
                'id' => $shipmentIds,
            ],
            $context,
            [
                'orders',
            ],
        );

        $operationResultSetWithOrderId = [
            'successfullyOrPartlySuccessfullyProcessedShipmentIds' => $shipmentsOperationResultSet->getSuccessfullyOrPartlySuccessfullyProcessedShipmentIds(),
            'shipmentsOperationResultsWithOrderNumber' => [],
        ];
        /** @var ShipmentsOperationResult $shipmentsOperationResult */
        foreach ($shipmentsOperationResultSet->getShipmentsOperationResults() as $shipmentsOperationResult) {
            /** @var ShipmentEntity $shipment */
            $shipment = $shipments->get($shipmentsOperationResult->getProcessedShipmentIds()[0]);
            $operationResultSetWithOrderId['shipmentsOperationResultsWithOrderNumber'][] = [
                'orderNumber' => $shipment->getOrders()->first()->getOrderNumber(),
                'shipmentOperationResult' => $shipmentsOperationResult,
            ];
        }

        $this->entityManager->delete(
            ShipmentDefinition::class,
            $shipmentsOperationResultSet->getFailedProcessedShipmentIds(),
            $context,
        );

        $this->shipmentTrackingCodeUpdater->copyTrackingCodesOfShipmentsToOrders(
            $shipmentsOperationResultSet->getSuccessfullyOrPartlySuccessfullyProcessedShipmentIds(),
            $context,
        );

        return $operationResultSetWithOrderId;
    }

    public function createReturnShipmentBlueprintForOrder(
        string $orderId,
        ShipmentBlueprintCreationConfiguration $shipmentBlueprintCreationConfiguration,
        Context $context,
    ): ShipmentBlueprintCreationResult {
        $returnShipmentBlueprintCreationResults = $this->createShipmentBlueprints(
            [
                $orderId => $shipmentBlueprintCreationConfiguration,
            ],
            true,
            [
                $orderId => null,
            ],
            $context,
        );

        return $returnShipmentBlueprintCreationResults[0];
    }

    public function createReturnShipmentForOrder(
        ShipmentBlueprint $shipmentBlueprint,
        string $orderId,
        Context $context,
        array $shipmentPayload = [],
    ): ShipmentsOperationResultSet {
        return $this->createReturnShipments(
            [
                array_merge(
                    $shipmentPayload,
                    [
                        'orders' => [
                            [
                                'id' => $orderId,
                            ],
                        ],
                        'shipmentBlueprint' => $shipmentBlueprint,
                        'isReturnShipment' => true,
                    ],
                ),
            ],
            $context,
        );
    }

    /**
     * @return string[]
     */
    public function getTrackingUrlsForShipment(string $shipmentId, Context $context): array
    {
        /** @var ShipmentEntity $shipment */
        $shipment = $this->entityManager->findByPrimaryKey(ShipmentDefinition::class, $shipmentId, $context, [
            'trackingCodes',
        ]);
        $trackingCodes = $shipment->getTrackingCodes();

        if ($trackingCodes->count() === 0) {
            return [];
        }
        if ($trackingCodes->count() === 1 && $trackingCodes->first()->getTrackingUrl() !== null) {
            return [$trackingCodes->first()->getTrackingUrl()];
        }
        $carrierAdapter = $this->carrierAdapterRegistry->getCarrierAdapterByTechnicalName(
            $shipment->getCarrierTechnicalName(),
        );
        if ($carrierAdapter instanceof MultiTrackingCapability) {
            $trackingCodeIds = EntityCollectionExtension::getField($shipment->getTrackingCodes(), 'id');

            return [$carrierAdapter->generateTrackingUrlForTrackingCodes($trackingCodeIds, $context)];
        }

        return array_values(array_filter($trackingCodes->map(fn(TrackingCodeEntity $trackingCodeEntity) => $trackingCodeEntity->getTrackingUrl())));
    }

    public function cancelShipment(string $shipmentId, Context $context): ShipmentsOperationResultSet
    {
        /** @var ShipmentEntity $shipment */
        $shipment = $this->entityManager->getByPrimaryKey(ShipmentDefinition::class, $shipmentId, $context, [
            'carrier',
            'salesChannel',
        ]);

        $carrierConfiguration = $this->configService->getConfigForSalesChannel(
            $shipment->getCarrier()->getConfigDomain(),
            $shipment->getSalesChannelId(),
        );

        $methodName = '';
        if ($shipment->getIsReturnShipment()) {
            $returnCancellationCapability = $this->carrierAdapterRegistry->getReturnShipmentCancellationCapability(
                $shipment->getCarrierTechnicalName(),
            );
            $result = $returnCancellationCapability->cancelReturnShipments([$shipment->getId()], $carrierConfiguration, $context);
            $methodName = 'cancelReturnShipments';
        } else {
            $cancellationCapability = $this->carrierAdapterRegistry->getCancellationCapability(
                $shipment->getCarrierTechnicalName(),
            );
            $result = $cancellationCapability->cancelShipments([$shipment->getId()], $carrierConfiguration, $context);
            $methodName = 'cancelShipments';
        }
        if (!$result->didProcessAllShipments([$shipment->getId()])) {
            throw new LogicException(sprintf(
                'Implementation of method %s for carrier "%s" did not process every passed ' .
                'shipment. Please make sure that the method returns a %s that in ' .
                'total includes every passed shipment at least once.',
                $methodName,
                $shipment->getCarrier()->getTechnicalName(),
                ShipmentsOperationResultSet::class,
            ));
        }

        $resultForShipment = $result->getResultForShipment($shipment->getId());
        if ($resultForShipment !== ShipmentsOperationResultSet::RESULT_SUCCESSFUL) {
            return $result;
        }

        $shipmentPayload = [
            'id' => $shipment->getId(),
            'cancelled' => true,
        ];
        $this->entityManager->update(ShipmentDefinition::class, [$shipmentPayload], $context);

        if ($shipment->getIsReturnShipment()) {
            $this->shipmentTrackingCodeUpdater->copyReturnTrackingCodesOfShipmentsToOrders([$shipment->getId()], $context);
        } else {
            $this->shipmentTrackingCodeUpdater->copyTrackingCodesOfShipmentsToOrders([$shipment->getId()], $context);
        }

        return $result;
    }

    /**
     * @return ShipmentBlueprintCreationResult[]
     */
    private function createShipmentBlueprints(
        array $shipmentBlueprintCreationConfigurationByOrderId,
        bool $isReturnShipmentBlueprint,
        array $productsInParcelByOrderId,
        Context $context,
    ): array {
        $shipmentBlueprintsWithOrderId = [];
        $orderIds = array_keys($shipmentBlueprintCreationConfigurationByOrderId);

        /** @var OrderCollection $orders */
        $orders = $this->entityManager->findBy(
            OrderDefinition::class,
            [
                'id' => $orderIds,
            ],
            $context,
            [
                'orderCustomer',
                'deliveries.shippingOrderAddress.country',
                'deliveries.shippingOrderAddress.countryState',
                'deliveries.shippingMethod.pickwareShippingShippingMethodConfig.carrier',
                'currency',
                'transactions.stateMachineState',
                'pickwareShippingShipments',
                'documents.documentType',
            ],
        );

        foreach ($orders as $order) {
            $shipmentBlueprint = new ShipmentBlueprint();

            $orderDelivery = OrderDeliveryCollectionExtension::primaryOrderDelivery($order->getDeliveries());
            $commonConfig = $this->configService->getCommonShippingConfigForSalesChannel($order->getSalesChannelId());
            $shippingMethodId = $shipmentBlueprintCreationConfigurationByOrderId[$order->getId()]->getShippingMethodId();
            $shippingMethod = null;
            if ($shippingMethodId) {
                $shippingMethod = $this->entityManager->findByPrimaryKey(
                    ShippingMethodDefinition::class,
                    $shippingMethodId,
                    $context,
                    [
                        'pickwareShippingShippingMethodConfig',
                        'pickwareShippingShippingMethodConfig.carrier',
                    ],
                );
            } else {
                $shippingMethod = $orderDelivery?->getShippingMethod();
            }
            /** @var ShippingMethodConfigEntity|null $shippingMethodConfig */
            $shippingMethodConfig = $shippingMethod?->getExtension('pickwareShippingShippingMethodConfig');

            if ($shippingMethodConfig?->getAddressConfiguration()->getUseAlternativeSenderAddress() === true) {
                $senderAddress = $shippingMethodConfig->getAddressConfiguration()->getAlternativeSenderAddress();
            } else {
                $senderAddress = $commonConfig->getSenderAddress();
            }
            $shipmentBlueprint->setSenderAddress($senderAddress);

            if ($shippingMethodConfig?->getAddressConfiguration()->getUseImporterOfRecordsAddress() === true) {
                $shipmentBlueprint->setImporterOfRecordsAddress($shippingMethodConfig->getAddressConfiguration()->getImporterOfRecordsAddress());
            }

            $receiverAddress = new Address();
            $removedAddressField = null;
            if ($orderDelivery) {
                $receiverAddress = Address::fromShopwareOrderAddress($orderDelivery->getShippingOrderAddress());
                $receiverAddress->setEmail($order->getOrderCustomer()->getEmail());
                $removedReceiverAddressFields = $this->privacyService->removePersonalDataFromOrderAddress(
                    $receiverAddress,
                    $order->getId(),
                    $context,
                );
                if (!$removedReceiverAddressFields->isEmpty()) {
                    $removedAddressField = RemovedFieldNode::fromRemovedFields('receiverAddress', $removedReceiverAddressFields);
                }

                $shipmentBlueprint->setReceiverAddress(
                    $this->addressCorrectingService->correctAddress($receiverAddress),
                );
            }

            // Swap sender and receiver for return shipment blueprints
            if ($isReturnShipmentBlueprint) {
                $shipmentBlueprint->setReceiverAddress(
                    $this->addressCorrectingService->correctAddress($shipmentBlueprint->getSenderAddress()),
                );
                $shipmentBlueprint->setSenderAddress($receiverAddress);
                if ($removedAddressField !== null) {
                    $removedAddressField->setName('senderAddress');
                }
            }

            if ($shippingMethodConfig && $shippingMethodConfig->getCarrier()->isActive()) {
                $carrierTechnicalName = $shippingMethodConfig->getCarrier()->getTechnicalName();

                if (
                    $isReturnShipmentBlueprint
                    && $this->carrierAdapterRegistry->hasReturnShipmentCapability($carrierTechnicalName)
                ) {
                    $shipmentBlueprint->setCarrierTechnicalName($carrierTechnicalName);
                    $resolvedConfig = $this->shipmentConfigurationService->getShipmentConfigurationForOrder(
                        $shippingMethodConfig,
                        $order->getId(),
                        true,
                        $context,
                        $shipmentBlueprint->getReceiverAddress(),
                    );
                    $shipmentBlueprint->setShipmentConfig($resolvedConfig);
                } elseif (!$isReturnShipmentBlueprint) {
                    $shipmentBlueprint->setCarrierTechnicalName($carrierTechnicalName);
                    $resolvedConfig = $this->shipmentConfigurationService->getShipmentConfigurationForOrder(
                        $shippingMethodConfig,
                        $order->getId(),
                        false,
                        $context,
                        $shipmentBlueprint->getReceiverAddress(),
                    );
                    $shipmentBlueprint->setShipmentConfig($resolvedConfig);
                }
            }

            $parcel = $this->parcelHydrator->hydrateParcelFromOrder(
                orderId: $order->getId(),
                config: new ParcelHydrationConfiguration(
                    filterProducts: $productsInParcelByOrderId[$order->getId()],
                ),
                context: $context,
            );
            $commonConfig->assignCustomsInformationToShipmentBlueprint($shipmentBlueprint);

            $customFields = $order->getCustomFields();
            $shipmentBlueprint->setMovementReferenceNumber(
                $customFields[MovementReferenceNumberCustomField::TECHNICAL_NAME] ?? null,
            );

            if ($order->getCurrency()) {
                $shipmentBlueprint->addFee(new Fee(
                    type: FeeType::ShippingCosts,
                    amount: new MoneyValue($order->getShippingTotal(), new Currency($order->getCurrency()->getIsoCode())),
                ));
            }

            $invoices = $order->getDocuments()->filter(
                fn(ShopwareDocumentEntity $document) => $document->getDocumentType()->getTechnicalName() === InvoiceRenderer::TYPE,
            );
            $invoices->sort(
                function(ShopwareDocumentEntity $a, ShopwareDocumentEntity $b) {
                    if ($a->getCreatedAt() === $b->getCreatedAt()) {
                        return 0;
                    }

                    return $a->getCreatedAt()->getTimestamp() > $b->getCreatedAt()->getTimestamp() ? -1 : 1;
                },
            );
            if ($invoices->first()) {
                $shipmentBlueprint->setInvoiceNumber($invoices->first()->getConfig()['custom']['invoiceNumber']);
                $shipmentBlueprint->setInvoiceDate((new DateTime($invoices->first()->getConfig()['documentDate']))->format('Y-m-d'));
            }

            $carrierAdapter = $shipmentBlueprint->getCarrierTechnicalName() ? $this->carrierAdapterRegistry->getCarrierAdapterByTechnicalName(
                $shipmentBlueprint->getCarrierTechnicalName(),
            ) : null;

            $shipmentBlueprintCreationConfiguration = $shipmentBlueprintCreationConfigurationByOrderId[$order->getId()] ?: ShipmentBlueprintCreationConfiguration::makeDefault();
            $parcels = $this->repackParcels(
                $parcel,
                $shipmentBlueprintCreationConfiguration,
                $shippingMethodConfig,
            );

            $isCashOnDeliveryPaymentMethod = $this->checkPaymentMethodsAreCashOnDeliveryEnabled($order->getTransactions(), $commonConfig->getCashOnDeliveryPaymentMethodIds());
            if ($carrierAdapter instanceof CashOnDeliveryCapability && $isCashOnDeliveryPaymentMethod) {
                $noCashOnDeliveryLabelExists = $this->checkNoCashOnDeliveryLabelsExistForOrder($order->getExtensions()['pickwareShippingShipments']);

                if ($noCashOnDeliveryLabelExists) {
                    $currency = $order->getCurrency();
                    $orderAmount = new MoneyValue(
                        $order->getAmountTotal(),
                        new Currency($currency->getIsoCode()),
                    );
                    $shipmentConfig = $shipmentBlueprint->getShipmentConfig();
                    $carrierAdapter->enableCashOnDeliveryInShipmentConfig($shipmentConfig, $orderAmount);
                    $shipmentBlueprint->setShipmentConfig($shipmentConfig);

                    if (count($parcels) >= 2) {
                        $parcels = [$parcel];
                        $this->notificationService->emit(CashOnDeliveryEnabledNotification::parcelPackingSkipped(
                            orderNumber: $order->getOrderNumber(),
                        ));
                    }
                } else {
                    $this->notificationService->emit(CashOnDeliveryEnabledNotification::cashOnDeliveryLabelAlreadyExists(
                        orderNumber: $order->getOrderNumber(),
                    ));
                }
            }

            $shipmentBlueprint->setCustomerReference($order->getOrderNumber());
            $shipmentBlueprint->setParcels($parcels);

            $this->eventDispatcher->dispatch(
                new ShipmentBlueprintCreatedEvent($shipmentBlueprint, $order->getId(), $context),
                ShipmentBlueprintCreatedEvent::EVENT_NAME,
            );
            $removedFields = null;
            if ($removedAddressField !== null) {
                $removedFields = new RemovedFieldTree($removedAddressField);
            }
            $shipmentBlueprintsWithOrderId[] = new ShipmentBlueprintCreationResult(
                orderId: $order->getId(),
                shipmentBlueprint: $shipmentBlueprint,
                removedFields: $removedFields,
            );
        }

        return $shipmentBlueprintsWithOrderId;
    }

    /**
     * @param string[] $codEnabledPaymentMethodIds
     */
    private function checkPaymentMethodsAreCashOnDeliveryEnabled(OrderTransactionCollection $orderTransactions, array $codEnabledPaymentMethodIds): bool
    {
        $primaryOrderTransaction = OrderTransactionCollectionExtension::getPrimaryOrderTransaction($orderTransactions);

        if (!$primaryOrderTransaction) {
            return false;
        }

        return in_array($primaryOrderTransaction->getPaymentMethodId(), $codEnabledPaymentMethodIds);
    }

    private function checkNoCashOnDeliveryLabelsExistForOrder(ShipmentCollection $orderShipments): bool
    {
        return $orderShipments->reduce(
            fn(bool $carry, ShipmentEntity $item) =>
                $carry
                && (
                    $item->isCancelled() || !$item->getCashOnDeliveryEnabled()
                ),
            true,
        );
    }

    /**
     * Fill the payload for the shipments with necessary information, so we can save it to the DB
     */
    private function completeShipmentPayloads(
        array $shipmentPayloads,
        object $carrierAdapter,
        Context $context,
    ): array {
        $orderPayloads = array_merge(array_map(
            fn(array $shipmentPayload) => $shipmentPayload['orders'],
            $shipmentPayloads,
        ));

        foreach ($orderPayloads as $orderPayload) {
            $orderIds[] = array_map(
                fn(array $order) => $order['id'],
                $orderPayload,
            );
        }

        $orderIds = array_merge(...$orderIds);

        /** @var OrderCollection $orders */
        $orders = $this->entityManager->findBy(
            OrderDefinition::class,
            [
                'id' => $orderIds,
            ],
            $context,
            [
                'salesChannel',
                'deliveries',
            ],
        );

        foreach ($shipmentPayloads as &$shipmentPayload) {
            $shipmentPayload['id'] ??= Uuid::randomHex();
            unset($shipmentPayload['carrier']);
            $shipmentPayload['carrierTechnicalName'] = $shipmentPayload['shipmentBlueprint']->getCarrierTechnicalName();
            unset($shipmentPayload['salesChannel']);
            $shipmentPayload['salesChannelId'] = $orders->get($shipmentPayload['orders'][0]['id'])->getSalesChannelId();
            if ($carrierAdapter instanceof CashOnDeliveryCapability) {
                $shipmentPayload['cashOnDeliveryEnabled'] = $carrierAdapter->isCashOnDeliveryEnabledInShipmentConfig(
                    $shipmentPayload['shipmentBlueprint']->getShipmentConfig(),
                );
            }
        }

        return $shipmentPayloads;
    }

    private function createShipments(array $shipmentsPayloads, Context $context): ShipmentsOperationResultSet
    {
        $carrierTechnicalNames = array_values(array_unique(array_map(
            fn(array $shipmentPayload) => $shipmentPayload['shipmentBlueprint']->getCarrierTechnicalName(),
            $shipmentsPayloads,
        )));
        if (count($carrierTechnicalNames) !== 1) {
            throw new LogicException('Multiple carriers are not supported at this point.');
        }
        $carrierTechnicalName = $carrierTechnicalNames[0];

        if ($carrierTechnicalName === null) {
            throw ShipmentException::carrierNotSelected();
        }

        /** @var CarrierEntity $carrier */
        $carrier = $this->entityManager->findByPrimaryKey(
            CarrierDefinition::class,
            $carrierTechnicalName,
            $context,
        );

        if (!$carrier->isActive()) {
            throw ShipmentException::carrierNotActivated($carrier);
        }

        $carrierAdapter = $this->carrierAdapterRegistry->getCarrierAdapterByTechnicalName($carrier->getTechnicalName());

        $shipmentsPayloads = $this->completeShipmentPayloads($shipmentsPayloads, $carrierAdapter, $context);
        $this->entityManager->create(ShipmentDefinition::class, $shipmentsPayloads, $context);
        $shipmentIds = array_map(fn(array $shipmentPayload) => $shipmentPayload['id'], $shipmentsPayloads);

        $salesChannelIds = array_values(array_unique(array_map(
            fn(array $shipmentPayload) => $shipmentPayload['salesChannelId'],
            $shipmentsPayloads,
        )));
        if (count($salesChannelIds) !== 1) {
            throw new LogicException('Multiple sales channels are not supported at this point.');
        }
        $salesChannelId = $salesChannelIds[0];
        $carrierConfig = $this->configService->getConfigForSalesChannel($carrier->getConfigDomain(), $salesChannelId);

        try {
            $result = $carrierAdapter->registerShipments($shipmentIds, $carrierConfig, $context);
        } catch (Throwable $e) {
            $this->entityManager->delete(ShipmentDefinition::class, $shipmentIds, $context);

            throw $e;
        }

        if (!$result->didProcessAllShipments($shipmentIds)) {
            throw new LogicException(sprintf(
                'Implementation of method registerShipments for carrier adapter "%s" did not process every passed ' .
                'ShipmentEntity. Please make sure that the method returns a ShipmentsOperationResultSet that in ' .
                'total includes every passed ShipmentEntity at least once.',
                get_class($carrierAdapter),
            ));
        }

        return $result;
    }

    private function createReturnShipments(array $shipmentsPayloads, Context $context): ShipmentsOperationResultSet
    {
        $carrierTechnicalNames = array_values(array_unique(array_map(
            fn(array $shipmentPayload) => $shipmentPayload['shipmentBlueprint']->getCarrierTechnicalName(),
            $shipmentsPayloads,
        )));
        if (count($carrierTechnicalNames) !== 1) {
            throw new LogicException('Multiple carriers are not supported at this point.');
        }
        $carrierTechnicalName = $carrierTechnicalNames[0];

        if ($carrierTechnicalName === null) {
            throw ShipmentException::carrierNotSelected();
        }

        /** @var CarrierEntity $carrier */
        $carrier = $this->entityManager->findByPrimaryKey(
            CarrierDefinition::class,
            $carrierTechnicalName,
            $context,
        );

        if (!$carrier->isActive()) {
            throw ShipmentException::carrierNotActivated($carrier);
        }

        $carrierAdapter = $this->carrierAdapterRegistry->getReturnShipmentsCapability($carrier->getTechnicalName());

        $shipmentsPayloads = $this->completeShipmentPayloads($shipmentsPayloads, $carrierAdapter, $context);
        $this->entityManager->create(ShipmentDefinition::class, $shipmentsPayloads, $context);
        $shipmentIds = array_map(fn(array $shipmentPayload) => $shipmentPayload['id'], $shipmentsPayloads);

        $salesChannelIds = array_values(array_unique(array_map(
            fn(array $shipmentPayload) => $shipmentPayload['salesChannelId'],
            $shipmentsPayloads,
        )));
        if (count($salesChannelIds) !== 1) {
            throw new LogicException('Multiple sales channels are not supported at this point.');
        }
        $salesChannelId = $salesChannelIds[0];

        $carrierConfig = $this->configService->getConfigForSalesChannel($carrier->getConfigDomain(), $salesChannelId);

        try {
            /** @var ShipmentsOperationResultSet $result */
            $result = $carrierAdapter->registerReturnShipments(
                $shipmentIds,
                $carrierConfig,
                $context,
            );
        } catch (Throwable $e) {
            $this->entityManager->delete(ShipmentDefinition::class, $shipmentIds, $context);

            throw $e;
        }

        if (!$result->didProcessAllShipments($shipmentIds)) {
            throw new LogicException(sprintf(
                'Implementation of method registerReturnShipments for carrier adapter "%s" did not process every passed ' .
                'ShipmentEntity. Please make sure that the method returns a ShipmentsOperationResultSet that in ' .
                'total includes every passed ShipmentEntity at least once.',
                $carrier->getTechnicalName(),
            ));
        }

        if (!$result->isAnyOperationResultSuccessful()) {
            $this->entityManager->delete(
                ShipmentDefinition::class,
                $shipmentIds,
                $context,
            );

            return $result;
        }

        $this->shipmentTrackingCodeUpdater->copyReturnTrackingCodesOfShipmentsToOrders($shipmentIds, $context);

        return $result;
    }

    private function repackParcels(
        Parcel $parcel,
        ShipmentBlueprintCreationConfiguration $shipmentBlueprintCreationConfiguration,
        ?ShippingMethodConfigEntity $shippingMethodConfig = null,
    ): array {
        if ($shippingMethodConfig) {
            $parcelPackingConfig = $shippingMethodConfig->getParcelPackingConfiguration();
            $parcel->setDimensions($parcelPackingConfig->getDefaultBoxDimensions());
            if ($shipmentBlueprintCreationConfiguration->getSkipParcelRepacking()) {
                // By setting the container limit to "infinity" we disable the repacking in multiple packages but still
                // get the filler weight and the weight overwrite set if necessary
                $parcelPackingConfig = $parcelPackingConfig->createCopy();
                $parcelPackingConfig->setMaxParcelWeight(null); // null == infinity
            }
            $parcels = $this->parcelPacker->repackParcel(
                $parcel,
                $parcelPackingConfig,
            );
        } else {
            $parcels = [$parcel];
        }

        if (count($parcels) > 1) {
            foreach ($parcels as $i => $repackedParcel) {
                if ($repackedParcel->getCustomerReference() === null) {
                    continue;
                }

                $repackedParcel->setCustomerReference(
                    sprintf('%s-%d', $repackedParcel->getCustomerReference(), $i + 1),
                );
            }
        }

        return $parcels;
    }
}
