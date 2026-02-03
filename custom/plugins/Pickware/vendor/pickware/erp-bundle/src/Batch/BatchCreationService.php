<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Batch;

use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\ExceptionHandling\UniqueIndexHttpException;
use Pickware\PickwareErpStarter\Batch\Model\BatchDefinition;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductEntity;
use Pickware\PickwareErpStarter\Product\Model\ProductTrackingProfile;
use Pickware\ShopwareExtensionsBundle\Context\ContextExtension;
use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\UserSnapshotGenerator;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;

/**
 * @phpstan-type BatchCreationPayload array{
 *     id?: string,
 *     productId: string,
 *     number?: string|null,
 *     comment?: string|null,
 *     productionDate?: string|null,
 *     bestBeforeDate?: string|null,
 *     customFields?: array<string, mixed>|null,
 *     tags?: list<array{id: string}>|null,
 * }
 */
class BatchCreationService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly UserSnapshotGenerator $userSnapshotGenerator,
    ) {}

    /**
     * @param list<BatchCreationPayload> $batchCreationPayloads
     */
    public function createBatches(array $batchCreationPayloads, Context $context): void
    {
        /** @var ProductCollection $products */
        $products = $this->entityManager->findBy(
            ProductDefinition::class,
            ['id' => array_column($batchCreationPayloads, 'productId')],
            $context,
            ['pickwareErpPickwareProduct'],
        );
        foreach ($batchCreationPayloads as $batchCreationPayload) {
            $product = $products->get($batchCreationPayload['productId']);
            $pickwareProduct = $product?->getExtensionOfType('pickwareErpPickwareProduct', PickwareProductEntity::class);
            if ($product === null || $pickwareProduct === null) {
                throw BatchException::productNotFound($batchCreationPayload['productId']);
            }

            match ($pickwareProduct->getTrackingProfile()) {
                ProductTrackingProfile::Number => $this->assertBatchNumberIsSet($product, $batchCreationPayload),
                ProductTrackingProfile::BestBeforeDate => $this->assertBestBeforeDateIsSet($product, $batchCreationPayload),
                ProductTrackingProfile::BestBeforeDateAndNumber => $this->assertBestBeforeDateAndNumberIsSet($product, $batchCreationPayload),
            };
        }

        $userId = ContextExtension::findUserId($context);
        if ($userId) {
            $userSnapshot = $this->userSnapshotGenerator->generateSnapshots([$userId], $context);
            foreach ($batchCreationPayloads as &$batchCreationPayload) {
                $batchCreationPayload['userId'] = $userId;
                $batchCreationPayload['userSnapshot'] = $userSnapshot[$userId];
            }
            unset($batchCreationPayload);
        }

        try {
            $this->entityManager->create(BatchDefinition::class, $batchCreationPayloads, $context);
        } catch (UniqueIndexHttpException $exception) {
            if ($exception->getErrorCode() !== BatchIdentifierUniqueIndexExceptionHandler::ERROR_CODE) {
                throw $exception;
            }

            throw BatchException::duplicateBatchNumber($exception);
        }
    }

    /**
     * @param BatchCreationPayload $batchCreationPayload
     */
    private function assertBatchNumberIsSet(ProductEntity $product, array $batchCreationPayload): void
    {
        if (!isset($batchCreationPayload['number'])) {
            throw BatchException::trackingProfileRequiresBatchNumber($product->getId(), $product->getProductNumber());
        }
    }

    /**
     * @param BatchCreationPayload $batchCreationPayload
     */
    private function assertBestBeforeDateIsSet(ProductEntity $product, array $batchCreationPayload): void
    {
        if (!isset($batchCreationPayload['bestBeforeDate'])) {
            throw BatchException::trackingProfileRequiresBestBeforeDate($product->getId(), $product->getProductNumber());
        }
    }

    /**
     * @param BatchCreationPayload $batchCreationPayload
     */
    private function assertBestBeforeDateAndNumberIsSet(ProductEntity $product, array $batchCreationPayload): void
    {
        if (!isset($batchCreationPayload['bestBeforeDate']) || !isset($batchCreationPayload['number'])) {
            throw BatchException::trackingProfileRequiresBestBeforeDateAndNumber($product->getId(), $product->getProductNumber());
        }
    }
}
