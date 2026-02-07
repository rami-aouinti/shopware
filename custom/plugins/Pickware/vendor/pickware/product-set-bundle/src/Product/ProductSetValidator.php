<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\Product;

use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityPreWriteValidationEvent;
use Pickware\DalBundle\EntityWriteValidationEventType;
use Pickware\ProductSetBundle\Model\ProductSetConfigurationDefinition;
use Pickware\ProductSetBundle\Model\ProductSetConfigurationEntity;
use Pickware\ProductSetBundle\Model\ProductSetDefinition;
use Pickware\ProductSetBundle\Model\ProductSetEntity;
use Pickware\ShopwareExtensionsBundle\Product\ProductNameFormatterService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductSetValidator implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private ProductNameFormatterService $productNameFormatterService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [

            EntityWriteValidationEventType::Pre->getEventName(ProductSetDefinition::ENTITY_NAME) => 'ensureProductSetCanBeCreated',
            EntityWriteValidationEventType::Pre->getEventName(ProductSetConfigurationDefinition::ENTITY_NAME) => 'ensureProductSetConfigurationCanBeCreated',
        ];
    }

    public function ensureProductSetCanBeCreated(EntityPreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if (!($command instanceof InsertCommand) || $command->getEntityName() !== ProductSetDefinition::ENTITY_NAME) {
                continue;
            }

            $productId = bin2hex($command->getPayload()['product_id']);

            /** @var ProductSetConfigurationEntity $productSetConfiguration */
            $productSetConfiguration = $this->entityManager->findOneBy(
                ProductSetConfigurationDefinition::class,
                [
                    'productId' => $productId,
                ],
                $event->getContext(),
                [
                    'product',
                ],
            );

            if (isset($productSetConfiguration)) {
                $productName = $this->getProductName($productId, $event->getContext());

                throw ProductSetUpdaterException::invalidProductSetCreationBecauseProductIsAlreadyAConfiguration(
                    $productName,
                    $productId,
                    $productSetConfiguration->getProduct()->getProductNumber(),
                );
            }
        }
    }

    public function ensureProductSetConfigurationCanBeCreated(EntityPreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if (
                !($command instanceof InsertCommand) || $command->getEntityName(
                ) !== ProductSetConfigurationDefinition::ENTITY_NAME
            ) {
                continue;
            }

            $productId = bin2hex($command->getPayload()['product_id']);

            /** @var ProductSetEntity $productSet */
            $productSet = $this->entityManager->findOneBy(
                ProductSetDefinition::class,
                [
                    'productId' => $productId,
                ],
                $event->getContext(),
                [
                    'product',
                ],
            );

            if (isset($productSet)) {
                $productName = $this->getProductName($productId, $event->getContext());

                throw ProductSetUpdaterException::invalidProductSetConfigurationCreationBecauseProductIsAlreadyAProductSet(
                    $productName,
                    $productId,
                    $productSet->getProduct()->getProductNumber(),
                );
            }
        }
    }

    private function getProductName(string $productId, Context $context): string
    {
        return $this->productNameFormatterService->getFormattedProductName(
            productId: $productId,
            templateOptions: [],
            context: $context,
        );
    }
}
