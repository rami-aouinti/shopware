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

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityPreWriteValidationEvent;
use Pickware\DalBundle\EntityPreWriteValidationEventDispatcher;
use Pickware\ProductSetBundle\Model\ProductSetConfigurationDefinition;
use Pickware\ProductSetBundle\Model\ProductSetDefinition;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\CascadeDeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductSetForVariantsUpdater implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManager $entityManager,
    ) {}

    public static function getSubscribedEvents()
    {
        return [
            ProductSetDefinition::ENTITY_WRITTEN_EVENT => 'productSetWritten',
            ProductSetDefinition::ENTITY_DELETED_EVENT => 'productSetDeleted',
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'productWritten',
            EntityPreWriteValidationEventDispatcher::getEventName(ProductSetDefinition::ENTITY_NAME) => [
                [
                    'triggerChangeSet',
                    10,
                ],
                [
                    'productSetPreWriteValidation',
                    0,
                ],
            ],
        ];
    }

    public function triggerChangeSet(EntityPreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if (
                $command instanceof DeleteCommand
                && ($command->getEntityName() === ProductSetConfigurationDefinition::ENTITY_NAME
                    || $command->getEntityName() === ProductSetDefinition::ENTITY_NAME)
            ) {
                $command->requestChangeSet();
            }
        }
    }

    public function productSetPreWriteValidation(EntityPreWriteValidationEvent $event): void
    {
        $productIdsByCreatedProductSets = [];
        $idsByDeletedProductSets = [];
        foreach ($event->getCommands() as $command) {
            if ($command instanceof InsertCommand) {
                $productIdsByCreatedProductSets[] = $command->getPayload()['product_id'];
            }
            if ($command instanceof DeleteCommand && !$command instanceof CascadeDeleteCommand) {
                $idsByDeletedProductSets[] = $command->getPrimaryKey()['id'];
            }
        }

        $this->validateCreationOfProductSet($productIdsByCreatedProductSets);

        $this->validateDeletionOfProductSet($idsByDeletedProductSets);
    }

    // Validates the creation of a new product set. Varaints of variant products must all be product sets or none of
    // them can be. If a product set is created for a variant while other variants are not product sets,
    // throw an exception.
    private function validateCreationOfProductSet(array $productIds): void
    {
        if (count($productIds) === 0) {
            return;
        }

        $parentProductSetIdsOfVariants = $this->connection->fetchAllAssociative(
            'SELECT
                DISTINCT
                    LOWER(HEX(product.`id`)) as productId,
                    LOWER(HEX(product.`parent_id`)) as parentProductId,
                    LOWER(HEX(productSet.`id`)) as parentProductSetId
            FROM product
            LEFT JOIN `pickware_product_set_product_set` productSet
            ON productSet.`product_id` = product.`parent_id`
            WHERE product.`id` IN (:productIds);',
            ['productIds' => $productIds],
            ['productIds' => ArrayParameterType::STRING],
        );

        if (count($parentProductSetIdsOfVariants) === 0) {
            return;
        }

        $productIdsToThrowException = [];
        foreach ($parentProductSetIdsOfVariants as $parentProductSetId) {
            if ($parentProductSetId['parentProductId'] !== null && $parentProductSetId['parentProductSetId'] === null) {
                $productIdsToThrowException[$parentProductSetId['parentProductId']] = $parentProductSetId['productId'];
            }
        }

        if (count($productIdsToThrowException) === 0) {
            return;
        }

        throw ProductSetUpdaterException::invalidProductSetCreationBecauseParentIsNotProductSet($productIdsToThrowException);
    }

    // Validates the deletion of a new product set. Varaints of variant products must all be product sets or none of
    // them can be. If a product set is deleted for a variant while other variants are still product sets,
    // throw an exception.
    private function validateDeletionOfProductSet(array $productSetIds): void
    {
        if (count($productSetIds) === 0) {
            return;
        }

        $parentProductSetIdsOfVariants = $this->connection->fetchAllAssociative(
            'SELECT
                DISTINCT
                    LOWER(HEX(product.`id`)) as productId,
                    LOWER(HEX(product.`parent_id`)) as parentProductId,
                    LOWER(HEX(productSetParent.`id`)) as parentProductSetId
            FROM `pickware_product_set_product_set` productSet
            LEFT JOIN product
            ON product.`id` = productSet.`product_id`
            LEFT JOIN `pickware_product_set_product_set` productSetParent
            ON productSetParent.`product_id` = product.`parent_id`
            WHERE productSet.`id` IN (:productSetIds);',
            ['productSetIds' => $productSetIds],
            ['productSetIds' => ArrayParameterType::STRING],
        );

        if (count($parentProductSetIdsOfVariants) === 0) {
            return;
        }

        $productIdsToThrowException = [];
        foreach ($parentProductSetIdsOfVariants as $parentProductSetId) {
            if ($parentProductSetId['parentProductId'] !== null && $parentProductSetId['parentProductSetId'] !== null) {
                $productIdsToThrowException[$parentProductSetId['parentProductId']] = $parentProductSetId['productId'];
            }
        }

        if (count($productIdsToThrowException) === 0) {
            return;
        }

        throw ProductSetUpdaterException::invalidProductSetDeletionBecauseParentIsAProductSet($productIdsToThrowException);
    }

    public function productWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $productIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            if ($writeResult->getOperation() === EntityWriteResult::OPERATION_INSERT) {
                $productIds[] = $writeResult->getPrimaryKey();
            }
        }

        $variantProductIdsOfProductSet = $this->connection->fetchAllAssociative(
            'SELECT
                DISTINCT
                    (LOWER(HEX(existingProductSet.`id`))) as existingProductSetId,
                    (LOWER(HEX(product.`id`))) as variantProductId,
                    (LOWER(HEX(productSetConfiguration.`id`))) as parentProductSetConfigurationId
            FROM product
            INNER JOIN `pickware_product_set_product_set` parentProductSet
            ON parentProductSet.`product_id` = product.`parent_id`
            LEFT JOIN `pickware_product_set_product_set_configuration` productSetConfiguration
            ON productSetConfiguration.`product_set_id` = parentProductSet.`id`
            LEFT JOIN `pickware_product_set_product_set` existingProductSet
            ON existingProductSet.`product_id` = product.`id`
            WHERE product.`id` IN (:productIds);',
            ['productIds' => array_map('hex2bin', $productIds)],
            ['productIds' => ArrayParameterType::STRING],
        );

        if (count($variantProductIdsOfProductSet) === 0) {
            return;
        }

        $variantProductSetsToBeCreated = [];
        foreach ($variantProductIdsOfProductSet as $variantProductIdOfProductSet) {
            if (!$variantProductIdOfProductSet['existingProductSetId']) {
                $variantProductSetsToBeCreated[] = [
                    'productId' => $variantProductIdOfProductSet['variantProductId'],
                    'productVersionId' => Defaults::LIVE_VERSION,
                ];
            }
        }
        $this->entityManager->create(
            ProductSetDefinition::class,
            $variantProductSetsToBeCreated,
            $event->getContext(),
        );

        // Delete all product set configurations of the parent product set if a variant gets created, since its only
        // allowed to customize the variants when the product set is a parent product
        $this->entityManager->delete(
            ProductSetConfigurationDefinition::class,
            array_filter(array_column($variantProductIdsOfProductSet, 'parentProductSetConfigurationId'), fn(?string $id) => isset($id)),
            $event->getContext(),
        );
    }

    public function productSetWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $productIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            if ($writeResult->getOperation() === EntityWriteResult::OPERATION_INSERT) {
                $productIds[] = $writeResult->getPayload()['productId'];
            }
        }

        if (count($productIds) === 0) {
            return;
        }

        $variantProductIds = $this->connection->fetchFirstColumn(
            'SELECT
                DISTINCT(LOWER(HEX(product.`id`)))
            FROM product
            WHERE product.parent_id IN (:productIds);',
            ['productIds' => array_map('hex2bin', $productIds)],
            ['productIds' => ArrayParameterType::STRING],
        );

        if (count($variantProductIds) === 0) {
            return;
        }

        $variantProductSetsToBeCreated = [];
        foreach ($variantProductIds as $variantProductId) {
            $variantProductSetsToBeCreated[] = [
                'productId' => $variantProductId,
                'productVersionId' => Defaults::LIVE_VERSION,
            ];
        }

        $this->entityManager->create(
            ProductSetDefinition::class,
            $variantProductSetsToBeCreated,
            $event->getContext(),
        );
    }

    public function productSetDeleted(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $productIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            if ($writeResult->getOperation() === EntityWriteResult::OPERATION_DELETE) {
                $productIds[] = bin2hex($writeResult->getChangeSet()->getBefore('product_id'));
            }
        }

        if (count($productIds) === 0) {
            return;
        }

        $variantProductIds = $this->connection->fetchFirstColumn(
            'SELECT
                DISTINCT(LOWER(HEX(productSet.`id`)))
            FROM `pickware_product_set_product_set` productSet
            LEFT JOIN product
            ON productSet.`product_id` = product.`id`
            WHERE product.`parent_id` IN (:productIds);',
            ['productIds' => array_map('hex2bin', $productIds)],
            ['productIds' => ArrayParameterType::STRING],
        );

        if (count($variantProductIds) === 0) {
            return;
        }

        $this->entityManager->delete(
            ProductSetDefinition::class,
            $variantProductIds,
            $event->getContext(),
        );
    }
}
