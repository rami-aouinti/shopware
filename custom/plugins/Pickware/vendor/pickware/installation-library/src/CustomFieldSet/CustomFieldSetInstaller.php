<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary\CustomFieldSet;

use Pickware\DalBundle\EntityManager;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationDefinition;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationEntity;
use Shopware\Core\System\CustomField\CustomFieldCollection;
use Shopware\Core\System\CustomField\CustomFieldDefinition;
use Shopware\Core\System\CustomField\CustomFieldEntity;

class CustomFieldSetInstaller
{
    public function __construct(private readonly EntityManager $entityManager) {}

    public function installCustomFieldSet(CustomFieldSet $customFieldSet, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(function() use ($customFieldSet, $context): void {
            $customFieldSetId = $this->ensureCustomFieldSetExists($customFieldSet, $context);
            $this->ensureCustomFieldSetRelationsExist($customFieldSetId, $customFieldSet->getRelations(), $context);
            $this->ensureCustomFieldsExist($customFieldSetId, $customFieldSet->getFields(), $context);
        });
    }

    public function ensureCustomFieldSetExists(CustomFieldSet $customFieldSet, Context $context): string
    {
        /** @var CustomFieldSetEntity|null $existingCustomFieldSet */
        $existingCustomFieldSet = $this->entityManager->findOneBy(
            CustomFieldSetDefinition::class,
            ['name' => $customFieldSet->getTechnicalName()],
            $context,
        );

        $upsertPayload = [
            'config' => $customFieldSet->getConfig(),
            'position' => $customFieldSet->getPosition(),
            'global' => $customFieldSet->isGlobal(),
        ];
        if ($existingCustomFieldSet) {
            $upsertPayload['id'] = $existingCustomFieldSet->getId();
        } else {
            $upsertPayload['id'] = Uuid::randomHex();
            $upsertPayload['name'] = $customFieldSet->getTechnicalName();
        }

        $this->entityManager->upsert(
            CustomFieldSetDefinition::class,
            [$upsertPayload],
            $context,
        );

        return $upsertPayload['id'];
    }

    public function ensureCustomFieldSetRelationsExist(
        string $customFieldSetId,
        array $customFieldSetRelationEntityNames,
        Context $context,
    ): void {
        /** @var CustomFieldSetRelationCollection $existingEntities */
        $existingEntities = $this->entityManager->findBy(
            CustomFieldSetRelationDefinition::class,
            [
                'customFieldSetId' => $customFieldSetId,
                'entityName' => $customFieldSetRelationEntityNames,
            ],
            $context,
        );
        $entityNamesOfExisingRelations = array_map(
            fn(CustomFieldSetRelationEntity $relationEntity) => $relationEntity->getEntityName(),
            $existingEntities->getElements(),
        );
        $payloads = [];
        foreach ($customFieldSetRelationEntityNames as $customFieldSetRelationEntityName) {
            if (!in_array($customFieldSetRelationEntityName, $entityNamesOfExisingRelations)) {
                $payloads[] = [
                    'customFieldSetId' => $customFieldSetId,
                    'entityName' => $customFieldSetRelationEntityName,
                ];
            }
        }

        $this->entityManager->create(
            CustomFieldSetRelationDefinition::class,
            $payloads,
            $context,
        );
    }

    public function ensureCustomFieldsExist(
        string $customFieldSetId,
        array $customFields,
        Context $context,
    ): void {
        /** @var CustomFieldCollection $existingEntities */
        $existingEntities = $this->entityManager->findBy(
            CustomFieldDefinition::class,
            ['name' => array_map(fn(CustomField $customField) => $customField->getTechnicalName(), $customFields)],
            $context,
        );
        $existingEntitiesIndexedByName = array_combine(
            array_map(fn(CustomFieldEntity $customFieldEntity) => $customFieldEntity->getName(), $existingEntities->getElements()),
            $existingEntities->getElements(),
        );

        $payloads = [];
        /** @var CustomField $customField */
        foreach ($customFields as $customField) {
            $upsertPayload = [
                'customFieldSetId' => $customFieldSetId,
                'config' => $customField->getConfig(),
                'active' => $customField->isActive(),
                'allowCustomerWrite' => $customField->allowsCustomerWrite(),
                'allowCartExpose' => $customField->allowsCartExpose(),
            ];

            if (array_key_exists($customField->getTechnicalName(), $existingEntitiesIndexedByName)) {
                $existingEntity = $existingEntitiesIndexedByName[$customField->getTechnicalName()];
                $upsertPayload['id'] = $existingEntity->getId();
            } else {
                $upsertPayload['id'] = Uuid::randomHex();
                // Immutable fields that cannot be updated.
                $upsertPayload['name'] = $customField->getTechnicalName();
                $upsertPayload['type'] = $customField->getType();
            }

            $payloads[] = $upsertPayload;
        }

        $this->entityManager->upsert(
            CustomFieldDefinition::class,
            $payloads,
            $context,
        );
    }
}
