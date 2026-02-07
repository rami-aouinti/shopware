<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DpdBundle\Adapter;

use Pickware\DalBundle\EntityManager;
use Pickware\DocumentBundle\Document\PageFormat;
use Pickware\DpdBundle\Api\DpdApiClientException;
use Pickware\DpdBundle\Api\DpdSoapApiClientFactory;
use Pickware\DpdBundle\Api\Requests\DpdRequestFactory;
use Pickware\DpdBundle\Config\DpdConfig;
use Pickware\DpdBundle\FeatureFlag\DpdFeatureFlag;
use Pickware\DpdBundle\Installation\DpdCarrier;
use Pickware\ShippingBundle\Carrier\AbstractCarrierAdapter;
use Pickware\ShippingBundle\Carrier\Capabilities\ReturnShipmentsRegistrationCapability;
use Pickware\ShippingBundle\Carrier\CarrierAdapterRegistry;
use Pickware\ShippingBundle\Carrier\PageFormatProviding;
use Pickware\ShippingBundle\Config\Config;
use Pickware\ShippingBundle\Shipment\Model\ShipmentCollection;
use Pickware\ShippingBundle\Shipment\Model\ShipmentDefinition;
use Pickware\ShippingBundle\Shipment\ShipmentsOperationResult;
use Pickware\ShippingBundle\Shipment\ShipmentsOperationResultSet;
use Pickware\ShippingBundle\Soap\SoapApiClient;
use Pickware\ShopwareExtensionsBundle\Context\ContextExtension;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(
    name: CarrierAdapterRegistry::CONTAINER_TAG,
    attributes: [
        'technicalName' => DpdCarrier::TECHNICAL_NAME,
        'featureFlagName' => DpdFeatureFlag::NAME,
    ],
)]
class DpdAdapter extends AbstractCarrierAdapter implements PageFormatProviding, ReturnShipmentsRegistrationCapability
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly DpdOrderFactory $orderFactory,
        private readonly DpdSoapApiClientFactory $dpdApiClientFactory,
        private readonly DpdResponseProcessor $dpdResponseProcessor,
    ) {}

    public function registerShipments(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet {
        $dpdConfig = new DpdConfig($carrierConfig);
        $localeCode = $this->resolveUserLocale($context);
        $dpdShipmentServiceApiClient = $this->createApiClient($dpdConfig, $localeCode);

        return $this->processShipments(
            shipmentIds: $shipmentIds,
            dpdConfig: $dpdConfig,
            dpdShipmentServiceApiClient: $dpdShipmentServiceApiClient,
            context: $context,
            isReturnShipment: false,
        );
    }

    public function registerReturnShipments(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet {
        $dpdConfig = new DpdConfig($carrierConfig);
        $localeCode = $this->resolveUserLocale($context);
        $dpdShipmentServiceApiClient = $this->createApiClient($dpdConfig, $localeCode);

        return $this->processShipments(
            shipmentIds: $shipmentIds,
            dpdConfig: $dpdConfig,
            dpdShipmentServiceApiClient: $dpdShipmentServiceApiClient,
            context: $context,
            isReturnShipment: true,
        );
    }

    /**
     * @return PageFormat[]
     */
    public function getPageFormats(): array
    {
        return DpdLabelSize::getSupportedPageFormats();
    }

    private function resolveUserLocale(Context $context): string
    {
        if (ContextExtension::hasUser($context)) {
            /** @var UserEntity $user */
            $user = $this->entityManager->getByPrimaryKey(
                UserDefinition::class,
                ContextExtension::getUserId($context),
                $context,
                ['locale'],
            );

            return $user->getLocale()?->getCode() ?? 'en-GB';
        }

        return 'en-GB';
    }

    private function createApiClient(DpdConfig $dpdConfig, string $localeCode): SoapApiClient
    {
        return $this->dpdApiClientFactory->createDpdShipmentServiceApiClient(
            $dpdConfig->getApiClientConfig($localeCode),
        );
    }

    /**
     * @param string[] $shipmentIds
     */
    private function processShipments(
        array $shipmentIds,
        DpdConfig $dpdConfig,
        SoapApiClient $dpdShipmentServiceApiClient,
        Context $context,
        bool $isReturnShipment,
    ): ShipmentsOperationResultSet {
        $shipmentsOperationResultSet = new ShipmentsOperationResultSet();
        /** @var ShipmentCollection $shipments */
        $shipments = $this->entityManager->findBy(ShipmentDefinition::class, ['id' => $shipmentIds], $context);

        $shipmentOrders = [];
        foreach ($shipments as $shipment) {
            $receiverAddress = $shipment->getShipmentBlueprint()->getReceiverAddress();
            $operationDescription = sprintf(
                'Create labels for shipment to %s %s',
                $receiverAddress->getFirstName(),
                $receiverAddress->getLastName(),
            );

            try {
                if ($isReturnShipment) {
                    $shipmentOrders[] = $this->orderFactory->createReturnOrdersForShipment($shipment, $dpdConfig);
                } else {
                    $shipmentOrders[] = $this->orderFactory->createOrdersForShipment($shipment, $dpdConfig);
                }
            } catch (DpdAdapterException $exception) {
                $shipmentsOperationResultSet->addShipmentOperationResult(
                    ShipmentsOperationResult::createFailedOperationResult(
                        [$shipment->getId()],
                        $operationDescription,
                        [$exception->serializeToJsonApiError()],
                    ),
                );

                continue;
            }
        }

        if (!$shipmentOrders) {
            return $shipmentsOperationResultSet;
        }

        try {
            $response = $dpdShipmentServiceApiClient->sendRequest(
                DpdRequestFactory::makeStoreOrdersRequest(array_merge(...$shipmentOrders), $dpdConfig),
            );

            $shipmentsOperationResultSet = $this->dpdResponseProcessor->processCreateShipmentOrderResponse(
                shipmentCreationResponse: $response,
                shipmentsOperationResultSet: $shipmentsOperationResultSet,
                dpdConfig: $dpdConfig,
                context: $context,
                isReturnShipment: $isReturnShipment,
            );
        } catch (DpdApiClientException $exception) {
            $shipmentsOperationResultSet->addShipmentOperationResult(
                ShipmentsOperationResult::createFailedOperationResult(
                    $shipmentIds,
                    'Creating labels',
                    [$exception->serializeToJsonApiError()],
                ),
            );
        }

        return $shipmentsOperationResultSet;
    }
}
