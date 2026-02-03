<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockApi\Subscriber;

use Pickware\DalBundle\EntityPostWriteValidationEvent;
use Pickware\DalBundle\EntityWriteValidationEventType;
use Pickware\PickwareErpStarter\Config\Config;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\Product\PickwareProductInitializer;
use Pickware\PickwareErpStarter\StockApi\AvailableStockWriter;
use Pickware\PickwareErpStarter\StockApi\AvailableStockWriterException;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Listens to changes made to the field "stock" of a product and initiates the corresponding absolute stock change.
 */
class ProductStockSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly AvailableStockWriter $availableStockWriter,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteValidationEventType::Post->getEventName(ProductDefinition::ENTITY_NAME) => [
                'postWriteValidation',
                PickwareProductInitializer::SUBSCRIBER_PRIORITY - 1,
            ],
        ];
    }

    public function postWriteValidation($event): void
    {
        if (!($event instanceof EntityPostWriteValidationEvent)) {
            // The subscriber is probably instantiated in its old version (with the Shopware PreWriteValidationEvent) in
            // the container and will be updated on the next container rebuild (next request). Early return.
            return;
        }

        if ($event->getDefinitionClassName() !== ProductDefinition::class) {
            return;
        }

        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }
        if (!$this->config->isStockInitialized()) {
            return;
        }

        $writeCommands = $event->getCommands();
        $newProductStocks = [];
        $existingProductStocks = [];
        $violations = new ConstraintViolationList();
        foreach ($writeCommands as $writeCommand) {
            $payload = $writeCommand->getPayload();
            $primaryKey = $writeCommand->getPrimaryKey();
            // Filter out instances of EntityWriteResult with empty payload. Somehow they are introduced by a bug in
            // the Shopware DAL.
            if (count($payload) === 0) {
                continue;
            }
            if (bin2hex($primaryKey['version_id']) !== Defaults::LIVE_VERSION) {
                continue;
            }
            if (!array_key_exists('stock', $payload)) {
                continue;
            }

            $isNewProduct = $writeCommand->getEntityExistence() && !$writeCommand->getEntityExistence()->exists();
            $productId = bin2hex($primaryKey['id']);
            if ($isNewProduct) {
                $newProductStocks[$productId] = $payload['stock'];
            } else {
                $existingProductStocks[$productId] = $payload['stock'];
            }
        }

        try {
            if (count($existingProductStocks) > 0) {
                $this->availableStockWriter->setAvailableStockForProducts(
                    ProductQuantityImmutableCollection::create(array_map(
                        fn(string $productId, int $quantity) => new ProductQuantity($productId, $quantity),
                        array_keys($existingProductStocks),
                        array_values($existingProductStocks),
                    )),
                    StockLocationReference::productAvailableStockChange(),
                    $event->getContext(),
                );
            }
            if (count($newProductStocks) > 0) {
                $this->availableStockWriter->setAvailableStockForProducts(
                    ProductQuantityImmutableCollection::create(array_map(
                        fn(string $productId, int $quantity) => new ProductQuantity($productId, $quantity),
                        array_keys($newProductStocks),
                        array_values($newProductStocks),
                    )),
                    StockLocationReference::initialization(),
                    $event->getContext(),
                );
            }
        } catch (AvailableStockWriterException $e) {
            foreach ($e->getProductIds() as $productId) {
                $violations->add(new ConstraintViolation(
                    message: $e->getMessage(),
                    messageTemplate: $e->getMessage(),
                    parameters: [],
                    root: null,
                    propertyPath: sprintf('%s/stock', $productId),
                    invalidValue: $newProductStocks[$productId] ?? $existingProductStocks[$productId],
                ));
            }
        }

        if ($violations->count() === 0) {
            return;
        }

        $event->addViolation(new WriteConstraintViolationException($violations));
    }
}
