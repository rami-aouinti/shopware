<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\CustomField;

use Pickware\DalBundle\ContextFactory;
use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\CustomField\CustomFieldEntity;

class DocumentCustomFieldService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ContextFactory $contextFactory,
        private readonly CustomFieldFormatter $customFieldFormatter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getCustomFieldsConfig(string $orderId, string $documentTypeTechnicalName, Context $context): array
    {
        /** @var OrderEntity $orderInContextLanguage */
        $orderInContextLanguage = $this->entityManager->getByPrimaryKey(
            OrderDefinition::class,
            $orderId,
            $context,
        );
        $localizedContext = $this->contextFactory->createLocalizedContext(
            $orderInContextLanguage->getLanguageId(),
            $context,
        );
        /** @var OrderEntity $orderInOrderLanguage */
        $orderInOrderLanguage = $localizedContext->enableInheritance(fn(Context $inheritanceContext) => $this->entityManager->getByPrimaryKey(
            OrderDefinition::class,
            $orderId,
            $inheritanceContext,
            ['lineItems.product.customFields'],
        ));
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $documentTypeTechnicalName));
        $criteria->addAssociation('pickwareErpDocumentTypeCustomFieldMappings.customField.customFieldSet');
        $criteria->getAssociation('pickwareErpDocumentTypeCustomFieldMappings')->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));
        $criteria->addSorting(new FieldSorting('pickwareErpDocumentTypeCustomFieldMappings.position', FieldSorting::ASCENDING));
        /** @var DocumentTypeEntity $documentType */
        $documentType = $this->entityManager->getOneBy(
            DocumentTypeDefinition::class,
            $criteria,
            $context,
        );
        /** @var EntityCollection<DocumentTypeCustomFieldMappingEntity> $documentTypeCustomFieldMappings */
        $documentTypeCustomFieldMappings = $documentType->getExtension('pickwareErpDocumentTypeCustomFieldMappings');

        $configs = [];
        foreach ($documentTypeCustomFieldMappings as $documentTypeCustomFieldMapping) {
            /** @var CustomFieldEntity $customField */
            $customField = $documentTypeCustomFieldMapping->getCustomField();
            if (!$this->customFieldFormatter->isCustomFieldSupported($customField->getId(), $context)) {
                continue;
            }

            $configs[] = $this->getConfigForCustomField(
                $customField,
                $orderInOrderLanguage,
                $documentTypeCustomFieldMapping->getEntityType(),
                $context,
            );
        }

        return array_merge_recursive(...$configs);
    }

    /**
     * @return array<string, mixed>
     */
    private function getConfigForCustomField(CustomFieldEntity $customField, OrderEntity $orderInOrderLanguage, DocumentCustomFieldTargetEntityType $entityToAddCustomFieldTo, Context $context): array
    {
        $customFieldLabel = $this->customFieldFormatter->formatCustomFieldLabel($customField->getId(), $orderInOrderLanguage->getLanguageId(), $context);
        $customFieldType = $customField->getConfig()['customFieldType'] ?? $customField->getType();
        switch ($entityToAddCustomFieldTo) {
            case DocumentCustomFieldTargetEntityType::Order:
                $customFieldOrderValue = $orderInOrderLanguage->getCustomFieldsValue($customField->getName());
                // Note that Shopware will also set the value on the entity to null if the custom field set is not
                // configured to be compatible with the entity type. Therefore, the check below also covers this case.
                if ($customFieldOrderValue !== null) {
                    return [
                        'pickwareErpOrderCustomFields' => [
                            $customField->getId() => [
                                'label' => $customFieldLabel,
                                'value' => $this->customFieldFormatter->formatCustomFieldValue(
                                    $customField->getId(),
                                    $customFieldOrderValue,
                                    $orderInOrderLanguage->getLanguageId(),
                                    $orderInOrderLanguage->getCurrencyId(),
                                    $context,
                                ),
                                'type' => $customFieldType,
                            ],
                        ],
                    ];
                }
                break;
            case DocumentCustomFieldTargetEntityType::Product:
                $productCustomFields = [];
                $products = ImmutableCollection::create($orderInOrderLanguage->getLineItems())
                    ->compactMap(fn(OrderLineItemEntity $lineItem) => $lineItem->getProduct())
                    ->groupBy(
                        fn(ProductEntity $product) => $product->getId(),
                        fn(ImmutableCollection $productsWithSameId) => $productsWithSameId->first(),
                    );
                foreach ($products as $product) {
                    $customFieldProductValue = $product->getCustomFieldsValue($customField->getName());
                    if ($customFieldProductValue !== null) {
                        $productCustomFields[$product->getId()] = [
                            $customField->getId() => [
                                'label' => $customFieldLabel,
                                'value' => $this->customFieldFormatter->formatCustomFieldValue(
                                    $customField->getId(),
                                    $customFieldProductValue,
                                    $orderInOrderLanguage->getLanguageId(),
                                    $orderInOrderLanguage->getCurrencyId(),
                                    $context,
                                ),
                                'type' => $customFieldType,
                            ],
                        ];
                    }
                }

                return ['pickwareErpProductCustomFields' => $productCustomFields];
        }

        return [];
    }
}
