<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\Order;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Exception;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityPreWriteValidationEvent;
use Pickware\DalBundle\EntityPreWriteValidationEventDispatcher;
use function Pickware\DebugBundle\Profiling\trace;
use Pickware\DebugBundle\Profiling\TracingTag;
use Pickware\PickwareErpStarter\Translation\Translator;
use Pickware\ProductSetBundle\Model\ProductSetConfigurationCollection;
use Pickware\ProductSetBundle\Model\ProductSetConfigurationDefinition;
use Pickware\ProductSetBundle\Model\ProductSetConfigurationEntity;
use Pickware\ProductSetBundle\Model\ProductSetDefinition;
use Pickware\ShopwareExtensionsBundle\Product\ProductNameFormatterService;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderUpdater implements EventSubscriberInterface
{
    public const PICKWARE_PRODUCT_SET_CHILD_LINE_PAYLOAD_FIELD_KEY = 'pickwareProductSetConfigurationSnapshot';

    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManager $entityManager,
        private readonly ProductNameFormatterService $productNameFormatterService,
        private readonly Translator $translator,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // Ensure that the order manipulation is done before any other subscriber.
            CheckoutOrderPlacedEvent::class => [
                'orderPlaced',
                PHP_INT_MAX,
            ],
            OrderEvents::ORDER_LINE_ITEM_WRITTEN_EVENT => [
                'orderLineItemWritten',
                PHP_INT_MAX,
            ],
            EntityPreWriteValidationEventDispatcher::getEventName(OrderLineItemDefinition::ENTITY_NAME) => 'triggerChangeSet',

        ];
    }

    public function triggerChangeSet(EntityPreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if (
                $command instanceof UpdateCommand
                && $command->getEntityName() === OrderLineItemDefinition::ENTITY_NAME
            ) {
                $command->requestChangeSet();
            }
        }
    }

    public function orderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $tags = [TracingTag::Stacktrace->getKey() => (new Exception())->getTraceAsString()];

        trace(__METHOD__, function() use ($event): void {
            $this->doOrderPlaced($event);
        }, tags: $tags);
    }

    private function doOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $subProductsFromOrderLineItem = $this->connection->fetchAllAssociative(
            'SELECT
                LOWER(HEX(`order_line_item`.`id`)) AS `orderLineItemId`,
                LOWER(HEX(`productSet`.`id`)) AS `productSetId`,
                LOWER(HEX(`productSetConfiguration`.`product_id`)) AS `productId`,
                LOWER(HEX(`productSetConfiguration`.`quantity`)) AS `productQuantity`
            FROM `order_line_item`
            INNER JOIN `pickware_product_set_product_set` `productSet`
                ON `productSet`.`product_id` = `order_line_item`.`product_id`
                AND `productSet`.`product_version_id` = `order_line_item`.`product_version_id`
            INNER JOIN `pickware_product_set_product_set_configuration` `productSetConfiguration`
                ON `productSet`.`id` = `productSetConfiguration`.`product_set_id`
            WHERE
                `order_line_item`.`order_id` = :orderId
                AND `order_line_item`.`order_version_id` = :liveVersionId',
            [
                'orderId' => hex2bin($event->getOrder()->getId()),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            ['orderIds' => ArrayParameterType::STRING],
        );

        if (count($subProductsFromOrderLineItem) === 0) {
            return;
        }

        $this->addProductSetSubProductToOrders($subProductsFromOrderLineItem, $event->getContext());
    }

    private function addProductSetSubProductToOrders(array $subProductsFromOrderLineItem, Context $context): void
    {
        /**
         * Sub products that will be added. Can happen for different order line items within the same order.
         *
         * @var ProductCollection $subProducts
         */
        $subProducts = $this->entityManager->findBy(
            ProductDefinition::class,
            ['id' => array_column($subProductsFromOrderLineItem, 'productId')],
            $context,
            ['cover'],
        );
        /**
         * Order line items of the main products of any product set of the current order.
         *
         * @var OrderLineItemCollection $orderLineItems
         */
        $orderLineItems = $this->entityManager->findBy(
            OrderLineItemDefinition::class,
            ['id' => array_column($subProductsFromOrderLineItem, 'orderLineItemId')],
            $context,
            [
                'product',
                'order.language.locale', // Necessary for the correct label translation
            ],
        );
        /** @var ProductSetConfigurationCollection $productSetConfigurations */
        $productSetConfigurations = $this->entityManager->findBy(
            ProductSetConfigurationDefinition::class,
            ['productSetId' => array_column($subProductsFromOrderLineItem, 'productSetId')],
            $context,
        );

        $subProductNames = $this->productNameFormatterService->getFormattedProductNames($subProducts->getIds(), [], $context);
        $this->translator->setTranslationLocale(
            $orderLineItems->first()->getOrder()->getLanguage()->getLocale()->getCode(),
            $context,
        );

        $orderLineItemPayloads = [];
        foreach ($subProductsFromOrderLineItem as $subProductFromOrderLineItem) {
            /** @var ProductSetConfigurationEntity $productSetConfiguration */
            $productSetConfiguration = $productSetConfigurations->filter(
                fn(ProductSetConfigurationEntity $configuration) =>
                    $configuration->getProductId() === $subProductFromOrderLineItem['productId']
                    && $configuration->getProductSetId() === $subProductFromOrderLineItem['productSetId'],
            )->first();
            /** @var ProductEntity $product */
            $product = $subProducts->get($subProductFromOrderLineItem['productId']);
            /** @var OrderLineItemEntity $orderLineItem */
            $orderLineItem = $orderLineItems->get($subProductFromOrderLineItem['orderLineItemId']);

            // Using the product set tax rules only works when the product set is sold with a quantity price definition.
            // This should be the case anyway. But to never crash the order checkout, we also provide a 19% fallback
            // value just in case.
            $taxRules = new TaxRuleCollection([new TaxRule(19.0)]);
            if ($orderLineItem->getPriceDefinition() instanceof QuantityPriceDefinition) {
                $taxRules = $orderLineItem->getPriceDefinition()->getTaxRules();
            }
            // To properly show "taxes" to the user and/or on documents we also add calculated taxes even though their
            // values are all 0.0.
            $calculatedTaxes = new CalculatedTaxCollection([]);
            foreach ($taxRules as $taxRule) {
                $calculatedTaxes->add(new CalculatedTax(0.0, $taxRule->getTaxRate(), 0.0));
            }

            $quantity = $orderLineItem->getQuantity() * $productSetConfiguration->getQuantity();
            $orderLineItemPayloads[] = [
                'productId' => $product->getId(),
                'quantity' => $quantity,
                'orderId' => $orderLineItem->getOrderId(),
                'parentId' => $orderLineItem->getId(),
                'identifier' => $orderLineItem->getId() . '_' . $product->getId(),
                'referencedId' => $product->getId(),
                'coverId' => $product->getCover()?->getMediaId(),
                'states' => [State::IS_PHYSICAL],
                'label' => sprintf(
                    '%s %s: %s',
                    $this->translator->translate('pickware-product-set.product-set.name'),
                    $orderLineItem->getProduct()->getProductNumber(),
                    array_key_exists($product->getId(), $subProductNames) ? $subProductNames[$product->getId()] : $product->getName(),
                ),
                'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                'price' => new CalculatedPrice(0.0, 0.0, $calculatedTaxes, $taxRules, $quantity),
                'priceDefinition' => new QuantityPriceDefinition(0.0, $taxRules, $quantity),
                'payload' => [
                    'productNumber' => $product->getProductNumber(),
                    self::PICKWARE_PRODUCT_SET_CHILD_LINE_PAYLOAD_FIELD_KEY => [
                        'quantity' => $productSetConfiguration->getQuantity(),
                    ],
                ],
            ];
        }

        $this->entityManager->create(
            OrderLineItemDefinition::class,
            $orderLineItemPayloads,
            $context,
        );

        // While creating the order line items of the sub products, we also update the order line items of the
        // main products with the product set type to ensure they only get the type when the order line items
        // of the sub products are added. This ensures that the order line items are still of the type "product" when
        // the order line items of the sub products are not added yet.
        $this->entityManager->update(
            OrderLineItemDefinition::class,
            array_values(array_map(fn(OrderLineItemEntity $mainProductOrderLineItems) => [
                'id' => $mainProductOrderLineItems->getId(),
                'type' => ProductSetDefinition::LINE_ITEM_TYPE,
            ], $orderLineItems->getElements())),
            $context,
        );
    }

    public function orderLineItemWritten(EntityWrittenEvent $event): void
    {
        $tags = [TracingTag::Stacktrace->getKey() => (new Exception())->getTraceAsString()];

        trace(__METHOD__, function() use ($event): void {
            $this->doOrderLineItemWritten($event);
        }, tags: $tags);
    }

    private function doOrderLineItemWritten(EntityWrittenEvent $event): void
    {
        $orderLineItemIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            if (
                $writeResult->getOperation() === EntityWriteResult::OPERATION_UPDATE
                && $writeResult->getChangeSet()?->hasChanged('quantity')
            ) {
                $orderLineItemIds[] = $writeResult->getPrimaryKey();
            }
        }
        if (count($orderLineItemIds) === 0) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE `order_line_item` subOrderLineItem

            INNER JOIN `order_line_item` parentOrderLineItem
            ON parentOrderLineItem.`id` = subOrderLineItem.`parent_id`
            AND parentOrderLineItem.`version_id` = subOrderLineItem.`parent_version_id`
            AND parentOrderLineItem.`type` = :productSetLineItemType

            SET subOrderLineItem.`quantity` = CAST(IFNULL(JSON_EXTRACT(subOrderLineItem.`payload`, \'$.' . self::PICKWARE_PRODUCT_SET_CHILD_LINE_PAYLOAD_FIELD_KEY . '.quantity\'), 1) AS UNSIGNED) * parentOrderLineItem.`quantity`,
                subOrderLineItem.`price` = JSON_REPLACE(
                    subOrderLineItem.`price`,
                    \'$.quantity\',
                    CAST(IFNULL(JSON_EXTRACT(subOrderLineItem.`payload`, \'$.' . self::PICKWARE_PRODUCT_SET_CHILD_LINE_PAYLOAD_FIELD_KEY . '.quantity\'), 1) AS UNSIGNED) * parentOrderLineItem.`quantity`
                ),
                subOrderLineItem.`price_definition` = JSON_REPLACE(
                    subOrderLineItem.`price_definition`,
                    \'$.quantity\',
                    CAST(IFNULL(JSON_EXTRACT(subOrderLineItem.`payload`, \'$.' . self::PICKWARE_PRODUCT_SET_CHILD_LINE_PAYLOAD_FIELD_KEY . '.quantity\'), 1) AS UNSIGNED) * parentOrderLineItem.`quantity`
                )

            WHERE subOrderLineItem.`parent_id` IN (:parentOrderLineItemIds)
            AND subOrderLineItem.`version_id` = :liveVersionId',
            [
                'parentOrderLineItemIds' => array_map('hex2bin', $orderLineItemIds),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                'productSetLineItemType' => ProductSetDefinition::LINE_ITEM_TYPE,
            ],
            ['parentOrderLineItemIds' => ArrayParameterType::STRING],
        );
    }
}
