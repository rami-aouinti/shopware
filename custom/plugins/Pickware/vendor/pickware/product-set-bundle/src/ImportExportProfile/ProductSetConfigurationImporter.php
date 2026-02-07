<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\ImportExportProfile;

use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportException;
use Pickware\PickwareErpStarter\ImportExport\Importer;
use Pickware\PickwareErpStarter\ImportExport\ImportExportStateService;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementCollection;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementEntity;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\Validator;
use Pickware\ProductSetBundle\Model\ProductSetConfigurationDefinition;
use Pickware\ProductSetBundle\Model\ProductSetConfigurationEntity;
use Pickware\ProductSetBundle\Model\ProductSetDefinition;
use Pickware\ProductSetBundle\Model\ProductSetEntity;
use Pickware\ProductSetBundle\Product\ProductSetUpdaterException;
use Pickware\ValidationBundle\JsonValidator;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Throwable;

class ProductSetConfigurationImporter implements Importer
{
    public const TECHNICAL_NAME = 'product-set-configuration';
    public const VALIDATION_SCHEMA = [
        '$id' => 'pickware-product-set--import-export--product-set-configuration-import',
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'productSetProductNumber' => [
                'type' => 'string',
            ],
            'productSetConfigurationProductNumber' => [
                'type' => 'string',
            ],
            'quantity' => [
                'type' => 'integer',
            ],
        ],
        'required' => [
            'productSetProductNumber',
            'productSetConfigurationProductNumber',
            'quantity',
        ],
    ];

    private Validator $validator;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ProductSetConfigurationCsvRowNormalizer $normalizer,
        private readonly ImportExportStateService $importExportStateService,
        readonly JsonValidator $jsonValidator,
        private readonly int $batchSize,
    ) {
        $this->validator = new Validator($normalizer, self::VALIDATION_SCHEMA, $jsonValidator);
    }

    public function canBeParallelized(): bool
    {
        return false;
    }

    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    public function importChunk(string $importId, int $nextRowNumberToRead, Context $context): ?int
    {
        $criteria = EntityManager::createCriteriaFromArray(['importExportId' => $importId]);
        $criteria->addFilter(new RangeFilter('rowNumber', [
            RangeFilter::GTE => $nextRowNumberToRead,
            RangeFilter::LT => $nextRowNumberToRead + $this->batchSize,
        ]));

        /** @var ImportExportElementCollection $importElements */
        $importElements = $this->entityManager->findBy(
            ImportExportElementDefinition::class,
            $criteria,
            $context,
        );

        if ($importElements->count() === 0) {
            return null;
        }

        $normalizedRows = $importElements->map(fn(ImportExportElementEntity $importElement) => $this->normalizer->normalizeRow($importElement->getRowData()));
        $productsByProductNumber = $this->getProductNumberProductMapping($normalizedRows, $context);

        $createdProductSetIdByProductIds = [];
        foreach ($importElements->getElements() as $index => $importElement) {
            $normalizedRow = $normalizedRows[$index];

            $errors = new JsonApiErrors();

            /** @var ProductEntity $productSetProduct */
            $productSetProduct = $productsByProductNumber[mb_strtolower($normalizedRow['productSetProductNumber'])] ?? null;
            if (!$productSetProduct) {
                $errors->addError(ProductSetConfigurationImportException::productNotFoundError($normalizedRow['productSetProductNumber']));
            }

            /** @var ProductEntity $productSetConfigurationProduct */
            $productSetConfigurationProduct = $productsByProductNumber[mb_strtolower($normalizedRow['productSetConfigurationProductNumber'])] ?? null;
            if (!$productSetConfigurationProduct) {
                $errors->addError(ProductSetConfigurationImportException::productNotFoundError($normalizedRow['productSetConfigurationProductNumber']));
            }

            if ($this->failOnErrors($importElement->getId(), $errors, $context)) {
                continue;
            }

            try {
                if (!array_key_exists($productSetProduct->getId(), $createdProductSetIdByProductIds)) {
                    $createdProductSetIdByProductIds[$productSetProduct->getId()] = $this->createProductSetProduct(
                        $productSetProduct,
                        $context,
                    );
                }
                if (($normalizedRow['quantity'] ?? 0) === 0) {
                    $this->deleteProductSetConfiguration(
                        productSetConfigurationProductId: $productSetConfigurationProduct->getId(),
                        productSetId: $createdProductSetIdByProductIds[$productSetProduct->getId()],
                        context: $context,
                    );
                    $this->deleteProductSetsWithParentAndChildrenIfNecessary(
                        productSetId: $createdProductSetIdByProductIds[$productSetProduct->getId()],
                        context: $context,
                    );
                } else {
                    $this->upsertProductSetConfiguration(
                        normalizedRow: $normalizedRow,
                        productSetId: $createdProductSetIdByProductIds[$productSetProduct->getId()],
                        productSetConfigurationProductId: $productSetConfigurationProduct->getId(),
                        context: $context,
                    );
                }
            } catch (ProductSetUpdaterException $exception) {
                $errors->addError($exception->serializeToJsonApiError());
                $this->failOnErrors($importElement->getId(), $errors, $context);
            } catch (Throwable $exception) {
                throw ImportException::rowImportError($exception, $importElement->getRowNumber());
            }
        }

        $nextRowNumberToRead += $this->batchSize;

        return $nextRowNumberToRead;
    }

    public function validateHeaderRow(array $headerRow, Context $context): JsonApiErrors
    {
        return $this->validator->validateHeaderRow($headerRow, $context);
    }

    public function validateConfig(array $config): JsonApiErrors
    {
        return JsonApiErrors::noError();
    }

    private function getProductNumberProductMapping(array $normalizedRows, Context $context): array
    {
        $productNumbers = [
            ...array_column($normalizedRows, 'productSetProductNumber'),
            ...array_column($normalizedRows, 'productSetConfigurationProductNumber'),
        ];
        /** @var ProductCollection $products */
        $products = $this->entityManager->findBy(ProductDefinition::class, [
            'productNumber' => $productNumbers,
        ], $context);

        $productNumbers = $products->map(fn(ProductEntity $product) => mb_strtolower($product->getProductNumber()));

        return array_combine($productNumbers, $products->getElements());
    }

    private function failOnErrors(string $importElementId, JsonApiErrors $errors, Context $context): bool
    {
        if (count($errors) > 0) {
            $this->importExportStateService->failImportExportElement($importElementId, $errors, $context);

            return true;
        }

        return false;
    }

    private function createProductSetProduct(
        ProductEntity $product,
        Context $context,
    ): string {
        $this->createProductSetForParentProduct($product, $context);

        /** @var ProductSetEntity $productSet */
        $productSet = $this->entityManager->findOneBy(
            ProductSetDefinition::class,
            [
                'productId' => $product->getId(),
            ],
            $context,
        );

        $productSetId = $productSet?->getId() ?? Uuid::randomHex();

        if (!$productSet) {
            $this->entityManager->create(
                ProductSetDefinition::class,
                [
                    [
                        'id' => $productSetId,
                        'productId' => $product->getId(),
                    ],
                ],
                $context,
            );
        }

        return $productSetId;
    }

    private function createProductSetForParentProduct(
        ProductEntity $childProduct,
        Context $context,
    ): void {
        if (!$childProduct->getParentId()) {
            return;
        }

        /** @var ProductSetEntity $parentProductSet */
        $parentProductSet = $this->entityManager->findOneBy(
            ProductSetDefinition::class,
            [
                'productId' => $childProduct->getParentId(),
            ],
            $context,
        );

        if (!$parentProductSet) {
            $this->entityManager->create(
                ProductSetDefinition::class,
                [
                    [
                        'id' => Uuid::randomHex(),
                        'productId' => $childProduct->getParentId(),
                    ],
                ],
                $context,
            );
        }
    }

    private function upsertProductSetConfiguration(
        array $normalizedRow,
        string $productSetId,
        string $productSetConfigurationProductId,
        Context $context,
    ): void {
        /** @var ProductSetConfigurationEntity $productSetConfiguration */
        $productSetConfiguration = $this->entityManager->findOneBy(
            ProductSetConfigurationDefinition::class,
            [
                'productId' => $productSetConfigurationProductId,
                'productSetId' => $productSetId,
            ],
            $context,
        );

        $this->entityManager->upsert(
            ProductSetConfigurationDefinition::class,
            [
                [
                    'id' => $productSetConfiguration?->getId() ?? Uuid::randomHex(),
                    'productSetId' => $productSetId,
                    'productId' => $productSetConfigurationProductId,
                    'quantity' => $normalizedRow['quantity'],
                ],
            ],
            $context,
        );
    }

    private function deleteProductSetConfiguration(
        string $productSetConfigurationProductId,
        string $productSetId,
        Context $context,
    ): void {
        $this->entityManager->deleteByCriteria(
            ProductSetConfigurationDefinition::class,
            [
                'productId' => $productSetConfigurationProductId,
                'productSetId' => $productSetId,
            ],
            $context,
        );
    }

    private function deleteProductSetsWithParentAndChildrenIfNecessary(
        string $productSetId,
        Context $context,
    ): void {
        /** @var ProductSetEntity $productSet */
        $productSet = $this->entityManager->findByPrimaryKey(
            ProductSetDefinition::class,
            $productSetId,
            $context,
            [
                'product',
            ],
        );

        if (!$productSet) {
            return;
        }

        $parentProductSetId = null;
        if ($productSet->getProduct()->getParentId()) {
            $parentProductSetId = ($this->entityManager->findOneBy(
                ProductSetDefinition::class,
                [
                    'productId' => $productSet->getProduct()->getParentId(),
                ],
                $context,
            ))?->getId();
        }

        // Get the product set configurations of the parent product set and the provided product set. Otherwise, look
        // for the configurations of other variant products by the parent.
        $productSetConfigurationsCriteria = new Criteria();
        $productSetConfigurationsCriteria->addFilter(new MultiFilter(
            MultiFilter::CONNECTION_OR,
            array_filter([
                new EqualsAnyFilter('productSetId', array_filter([$productSet->getId(), $parentProductSetId])),
                ($parentProductSetId ? new EqualsFilter('productSet.product.parentId', $productSet->getProduct()->getParentId()) : null),
            ]),
        ));

        $productSetConfigurations = $this->entityManager->findBy(
            ProductSetConfigurationDefinition::class,
            $productSetConfigurationsCriteria,
            $context,
        );

        if (count($productSetConfigurations) > 0) {
            return;
        }

        $this->entityManager->delete(
            ProductSetDefinition::class,
            [
                $parentProductSetId ?? $productSet->getId(),
            ],
            $context,
        );
    }
}
