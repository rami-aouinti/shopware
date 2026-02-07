<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary\MailTemplate;

use Pickware\DalBundle\EntityManager;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeDefinition;
use Shopware\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;

class MailTemplateInstaller
{
    public function __construct(private readonly EntityManager $entityManager) {}

    public function installMailTemplate(
        MailTemplate $mailTemplate,
        Context $context,
    ): void {
        $this->installMailTemplateTypeWithEntityManager($mailTemplate, $context);
    }

    private function installMailTemplateTypeWithEntityManager(
        MailTemplate $mailTemplate,
        Context $context,
    ): void {
        $this->entityManager->runInTransactionWithRetry(function() use ($mailTemplate, $context): void {
            $mailTemplateTypeId = $this->ensureMailTemplateTypeWithEntityManager($mailTemplate, $context);
            $this->ensureMailTemplateWithEntityManager($mailTemplate, $mailTemplateTypeId, $context);
        });
    }

    private function ensureMailTemplateTypeWithEntityManager(
        MailTemplate $mailTemplate,
        Context $context,
    ): string {
        /** @var MailTemplateEntity|null $existingMailTemplateType */
        $existingMailTemplateType = $this->entityManager->findOneBy(
            MailTemplateTypeDefinition::class,
            ['technicalName' => $mailTemplate->getTechnicalName()],
            $context,
        );
        $id = $existingMailTemplateType ? $existingMailTemplateType->getId() : Uuid::randomHex();

        $this->entityManager->upsert(
            MailTemplateTypeDefinition::class,
            [
                [
                    'id' => $id,
                    'name' => $mailTemplate->getTypeNameTranslations(),
                    'technicalName' => $mailTemplate->getTechnicalName(),
                    'availableEntities' => $mailTemplate->getAvailableTemplateVariables(),
                ],
            ],
            $context,
        );

        return $id;
    }

    private function ensureMailTemplateWithEntityManager(
        MailTemplate $mailTemplate,
        string $mailTemplateTypeId,
        Context $context,
    ): string {
        // It is possible to add multiple mail templates for the same mail template type. Use the _oldest_ mail template
        // to ensure that it's the one we created upon installation.
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('mailTemplateTypeId', $mailTemplateTypeId))
            ->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING))
            ->setLimit(1);

        $existingMailTemplate = $this->entityManager->findOneBy(
            MailTemplateDefinition::class,
            $criteria,
            $context,
        );

        $id = $existingMailTemplate ? $existingMailTemplate->getId() : Uuid::randomHex();
        $upsertPayload = [
            'id' => $id,
            'mailTemplateTypeId' => $mailTemplateTypeId,
        ];

        // Do not overwrite content of existing mail templates, which might have been modified by the user
        if (!$existingMailTemplate) {
            $upsertPayload['translations'] = $mailTemplate->getMailTranslations();
        }

        $this->entityManager->upsert(
            MailTemplateDefinition::class,
            [
                $upsertPayload,
            ],
            $context,
        );

        return $id;
    }
}
