<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\ParcelHydration;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\DalBundle\EntityManager;
use Pickware\ShippingBundle\Config\ConfigService;
use Pickware\ShippingBundle\Parcel\ParcelItem;
use Pickware\ShippingBundle\ParcelHydration\Processor\CreateParcelItemsProcessor;
use Pickware\ShippingBundle\ParcelHydration\Processor\DistributeDistributableLineItemPricesProcessor;
use Pickware\ShippingBundle\ParcelHydration\Processor\DistributeParentPricesProcessor;
use Pickware\ShippingBundle\ParcelHydration\Processor\FilterProductsInParcelProcessor;
use Pickware\ShippingBundle\ParcelHydration\Processor\FilterSupportedParcelItemsProcessor;
use Pickware\ShippingBundle\ParcelHydration\Processor\ParcelItemsProcessor;
use Pickware\ShippingBundle\ParcelHydration\Processor\ProcessorContext;
use Pickware\ShippingBundle\ParcelHydration\Processor\SetCustomsInformationProcessor;
use Pickware\ShippingBundle\ParcelHydration\Processor\SetCustomsValueFallbackProcessor;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Currency\CurrencyEntity;

class ParcelItemHydrator
{
    public const PRODUCT_SET_TYPE = 'pickware_product_set_jit_product_set';

    /**
     * @param ParcelItemsProcessor[] $processors
     */
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ConfigService $configService,
        private readonly array $processors,
    ) {}

    /**
     * We use this constructor to explicitly control the order of processors.
     * The order of processors is critical because each processor depends on previous steps
     * being completed correctly.
     */
    public static function create(
        EntityManager $entityManager,
        ConfigService $configService,
        CreateParcelItemsProcessor $createParcelItemsProcessor,
        SetCustomsInformationProcessor $setCustomsInformationProcessor,
        DistributeDistributableLineItemPricesProcessor $distributeDistributableLineItemPricesProcessor,
        SetCustomsValueFallbackProcessor $setCustomsValueFallbackProcessor,
        DistributeParentPricesProcessor $distributeParentPricesProcessor,
        FilterSupportedParcelItemsProcessor $filterSupportedParcelItemsProcessor,
        FilterProductsInParcelProcessor $filterProductsInParcelProcessor,
    ): self {
        return new self(
            entityManager: $entityManager,
            configService: $configService,
            processors: [
                $createParcelItemsProcessor,
                $setCustomsInformationProcessor,
                $distributeDistributableLineItemPricesProcessor,
                $setCustomsValueFallbackProcessor,
                $distributeParentPricesProcessor,
                $filterSupportedParcelItemsProcessor,
                $filterProductsInParcelProcessor,
            ],
        );
    }

    /**
     * @return ParcelItem[]
     */
    public function hydrateParcelItemsFromOrder(string $orderId, ParcelHydrationConfiguration $config, Context $context): array
    {
        /** @var OrderEntity $order */
        $order = $this->entityManager->getByPrimaryKey(
            OrderDefinition::class,
            $orderId,
            $context,
            [
                'currency',
                'lineItems.product',
            ],
        );

        $orderLineItems = $order->getLineItems();

        /** @var CurrencyEntity $defaultCurrency */
        $defaultCurrency = $this->entityManager->getByPrimaryKey(
            CurrencyDefinition::class,
            Defaults::CURRENCY,
            $context,
        );

        $processorContext = new ProcessorContext(
            orderNumber: $order->getOrderNumber(),
            isOrderTaxFree: $order->getTaxStatus() === CartPrice::TAX_STATE_FREE,
            orderCurrency: $order->getCurrency(),
            defaultCurrency: $defaultCurrency,
            config: $config,
            commonShippingConfig: $this->configService->getCommonShippingConfigForSalesChannel($order->getSalesChannelId()),
            shopwareContext: $context,
        );

        $orderLineItemParcelMappings = array_values($orderLineItems?->map(
            fn(OrderLineItemEntity $orderLineItem): OrderLineItemParcelMapping => new OrderLineItemParcelMapping(
                orderLineItem: $orderLineItem,
                parcelItem: null,
            ),
        ) ?? throw new AssociationNotLoadedException('lineItems', $order));

        foreach ($this->processors as $processor) {
            $orderLineItemParcelMappings = $processor->process($orderLineItemParcelMappings, $processorContext);
        }

        return array_values(array_map(
            fn(OrderLineItemParcelMapping $item) => $item->getParcelItem(),
            array_filter($orderLineItemParcelMappings, fn(OrderLineItemParcelMapping $item) => $item->getParcelItem() !== null),
        ));
    }
}
