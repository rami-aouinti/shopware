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
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopware\Core\Framework\Context;

class MailTemplateUninstaller
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function uninstallMailTemplate(MailTemplate $mailTemplate, Context $context): void
    {
        /** @var MailTemplateTypeEntity $mailTemplateType */
        $mailTemplateType = $this->entityManager->findOneBy(
            MailTemplateTypeDefinition::class,
            ['technicalName' => $mailTemplate->getTechnicalName()],
            $context,
        );

        if (!$mailTemplateType) {
            return;
        }

        $this->entityManager->deleteByCriteria(
            MailTemplateDefinition::class,
            ['mailTemplateTypeId' => $mailTemplateType->getId()],
            $context,
        );
        $this->entityManager->delete(
            MailTemplateTypeDefinition::class,
            [$mailTemplateType->getId()],
            $context,
        );
    }
}
