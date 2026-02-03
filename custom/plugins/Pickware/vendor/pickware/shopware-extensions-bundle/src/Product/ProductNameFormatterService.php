<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\Product;

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\Context;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class ProductNameFormatterService
{
    private const DEFAULT_BASE_TEMPLATE = '{{ name | raw }}{{ renderedOptions | raw }}';
    private const DEFAULT_OPTIONS_TEMPLATE = ' ({{ options | join(", ") }})';
    private const DEFAULT_TEMPLATE_OPTIONS = [
        'baseTemplate' => self::DEFAULT_BASE_TEMPLATE,
        'optionsTemplate' => self::DEFAULT_OPTIONS_TEMPLATE,
    ];

    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param array{baseTemplate?: string, optionsTemplate?: string, optionsWithLabels?: bool} $templateOptions
     */
    public function getFormattedProductName(string $productId, array $templateOptions, Context $context): string
    {
        return $this->renderProductName(
            $this->getProductEntities([$productId], $context)->first(),
            array_merge(self::DEFAULT_TEMPLATE_OPTIONS, $templateOptions),
        );
    }

    /**
     * Returns a formatted product name for each of the given product ids. If a product does not exist or a name could
     * not be generated, an exception is thrown. Hence, each given product id will be part of the resulting array.
     *
     * @param string[] $productIds
     * @param array{baseTemplate?: string, optionsTemplate?: string, optionsWithLabels?: bool} $templateOptions
     * @return array<string, string> Associative array with product ids as keys and formatted product names as values.
     */
    public function getFormattedProductNames(array $productIds, array $templateOptions, Context $context): array
    {
        $uniqueProductIds = array_unique($productIds);
        $templateOptions = array_merge(self::DEFAULT_TEMPLATE_OPTIONS, $templateOptions);

        // Fetch products per batch while collecting all formatted product names to reduce peak memory usage.
        $formattedNames = [];
        $productIdsBatches = array_chunk($uniqueProductIds, 50);
        foreach ($productIdsBatches as $productIdsBatch) {
            $products = $this->getProductEntities($productIdsBatch, $context);
            $formattedNames = array_merge(
                $formattedNames,
                $products->map(fn(ProductEntity $product) => $this->renderProductName($product, $templateOptions)),
            );
        }

        return $formattedNames;
    }

    /**
     * @param string[] $productIds
     */
    private function getProductEntities(array $productIds, Context $context): ProductCollection
    {
        /** @var ProductCollection $products */
        $products = $context->enableInheritance(fn(Context $inheritanceContext) => $this->entityManager->findBy(
            ProductDefinition::class,
            ['id' => $productIds],
            $inheritanceContext,
            [
                // Load this association to automatically fill the ProductEntity::variant property
                'options.group',
            ],
        ));

        if ($products->count() !== count($productIds)) {
            throw ProductException::productsDoNotExist(array_diff($productIds, $products->getIds()));
        }

        return $products;
    }

    private function getProductName(ProductEntity $product): string
    {
        return $product->getTranslation('name') ?: ($product->getName() ?: '');
    }

    /**
     * @return string[]|null
     */
    private function getProductOptionNames(ProductEntity $product): ?array
    {
        $groupedOptions = $this->getGroupedOptions($product);

        if (!$groupedOptions) {
            return null;
        }

        return $groupedOptions->flatMap(
            fn(PropertyGroupEntity $optionsGroup) => $optionsGroup->getOptions()->map(
                fn(PropertyGroupOptionEntity $option) => $option->getTranslation('name') ?: $option->getName(),
            ),
        )->asArray();
    }

    /**
     * @return array<array{label: string, value: string}>|null
     */
    private function getProductOptions(ProductEntity $product): ?array
    {
        $groupedOptions = $this->getGroupedOptions($product);

        if (!$groupedOptions) {
            return null;
        }

        return $groupedOptions->flatMap(
            fn(PropertyGroupEntity $optionGroup) => $optionGroup->getOptions()->map(
                fn(PropertyGroupOptionEntity $option) => [
                    'label' => $optionGroup->getTranslation('name') ?: $optionGroup->getName(),
                    'value' => $option->getTranslation('name') ?: $option->getName(),
                ],
            ),
        )->asArray();
    }

    /**
     * @return ImmutableCollection<PropertyGroupEntity>|null
     */
    private function getGroupedOptions(ProductEntity $product): ?ImmutableCollection
    {
        if (!$product->getParentId() || !$product->getOptions() || $product->getOptions()->count() === 0) {
            return null;
        }

        $groupedOptions = $product->getOptions()->groupByPropertyGroups();
        // Sorts the option groups by position
        $groupedOptions->sortByPositions();
        // Sorts the options inside the option groups
        $groupedOptions->sortByConfig();

        return ImmutableCollection::fromArray($groupedOptions->getElements());
    }

    /**
     * @param array{baseTemplate: string, optionsTemplate: string, optionsWithLabels?: bool} $templateOptions
     */
    private function renderProductName(ProductEntity $product, array $templateOptions): string
    {
        $twig = new Environment(
            new ArrayLoader([
                'baseTemplate' => $templateOptions['baseTemplate'],
                'optionsTemplate' => $templateOptions['optionsTemplate'],
            ]),
            [
                'strict_variables' => true,
                'cache' => false,
            ],
        );

        $renderedOptions = '';
        $useOptionsWithLabels = $templateOptions['optionsWithLabels'] ?? false;
        $productOptions = $useOptionsWithLabels ? $this->getProductOptions($product) : $this->getProductOptionNames($product);

        if ($productOptions) {
            $renderedOptions = $twig->render('optionsTemplate', [
                'options' => $productOptions,
            ]);
        }

        return $twig->render('baseTemplate', [
            'name' => $this->getProductName($product),
            'renderedOptions' => $renderedOptions,
        ]);
    }
}
