<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Installation\Steps;

use Exception;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Reorder\ReorderMailEvent;
use Pickware\PickwareErpStarter\Reorder\ReorderMailTemplate;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Content\Flow\FlowDefinition;
use Shopware\Core\Content\Flow\FlowEntity;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigCollection;
use Shopware\Core\System\SystemConfig\SystemConfigDefinition;

/**
 * Note that this flow creation must be an installation step (and not a migration) because it depends on the mail
 * template and type "pickware_erp_reorder" which, itself, is created in an installation step.
 */
class CreateReorderNotificationFlowInstallationStep
{
    public const FLOW_ID = 'dfff3fc4aa0b4502bcf5ab1a506e1d49';

    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function install(Context $context): void
    {
        /** @var FlowEntity|null $existingFlow */
        $existingFlow = $this->entityManager->findByPrimaryKey(
            FlowDefinition::class,
            self::FLOW_ID,
            $context,
        );

        if ($existingFlow) {
            return;
        }

        /** @var MailTemplateTypeEntity $mailTemplateType */
        $mailTemplateType = $this->entityManager->findOneBy(
            MailTemplateTypeDefinition::class,
            ['technicalName' => ReorderMailTemplate::TECHNICAL_NAME],
            $context,
            ['mailTemplates'],
        );

        if (!$mailTemplateType || $mailTemplateType->getMailTemplates()->count() === 0) {
            throw new Exception(sprintf(
                'Cannot create reorder mail notification flow.
                 MailTemplateType %s / MailTemplate for reorder notifications not found',
                ReorderMailTemplate::TECHNICAL_NAME,
            ));
        }

        $mailTemplates = $mailTemplateType->getMailTemplates();
        // It is possible to add multiple mail templates for the same mail template type. Use the _oldest_ mail template
        // to ensure that it's the one, we created upon installation.
        $mailTemplates->sort(
            function(MailTemplateEntity $a, MailTemplateEntity $b) {
                if ($a->getCreatedAt() === $b->getCreatedAt()) {
                    return 0;
                }

                return $a->getCreatedAt()->getTimestamp() < $b->getCreatedAt()->getTimestamp() ? -1 : 1;
            },
        );

        /** @var MailTemplateEntity $mailTemplate */
        $mailTemplate = $mailTemplates->first();

        /** @var SystemConfigCollection $reorderConfig */
        $reorderConfig = $this->entityManager->findBy(
            SystemConfigDefinition::class,
            [
                'configurationKey' => [
                    'PickwareErpBundle.global-plugin-config.reorderNotificationEnabled',
                    'PickwareErpBundle.global-plugin-config.reorderNotificationRecipients',
                ],
            ],
            $context,
        );
        $reorderMailActive = $reorderConfig->filterByProperty(
            'configurationKey',
            'PickwareErpBundle.global-plugin-config.reorderNotificationEnabled',
        )->first();
        $reorderMailRecipients = $reorderConfig->filterByProperty(
            'configurationKey',
            'PickwareErpBundle.global-plugin-config.reorderNotificationRecipients',
        )->first();

        $mailActionSentConfig = [
            'mailTemplateTypeId' => $mailTemplateType->getId(),
            'mailTemplateId' => $mailTemplate->getId(),
            'recipient' => [
                'data' => [],
                'type' => 'admin',
            ],
        ];

        $data = [];
        if ($reorderMailRecipients && $reorderMailRecipients->getConfigurationValue()) {
            $recipientsArray = explode(',', $reorderMailRecipients->getConfigurationValue());
            foreach ($recipientsArray as $recipient) {
                $data[trim($recipient)] = trim($recipient);
            }

            $mailActionSentConfig['recipient'] = [
                'data' => $data,
                'type' => 'custom',
            ];
        }

        $createPayload[] = [
            'id' => self::FLOW_ID,
            'eventName' => ReorderMailEvent::EVENT_NAME,
            'name' => 'Pickware ERP Reorder Mail',
            'description' => 'Ist dieser Flow aktiviert, wird täglich eine E-Mail verschickt, in der alle Produkte gelistet sind, deren aktueller Bestand kleiner oder gleich dem festgelegten Mindestbestand ist. Die Uhrzeit zum Versand dieser E-Mail kann in den Einstellungen der Erweiterung "Pickware ERP" festgelegt werden.',
            'active' => $reorderMailActive ? $reorderMailActive->getConfigurationValue() : false,
            'sequences' => [
                [
                    'id' => Uuid::randomHex(),
                    'actionName' => SendMailAction::ACTION_NAME,
                    'config' => $mailActionSentConfig,
                ],
            ],
        ];

        $this->entityManager->runInTransactionWithRetry(function() use ($createPayload, $context): void {
            // Remove old config values if they still exist
            $this->entityManager->deleteByCriteria(
                SystemConfigDefinition::class,
                [
                    'configurationKey' => [
                        'PickwareErpBundle.global-plugin-config.reorderNotificationEnabled',
                        'PickwareErpBundle.global-plugin-config.reorderNotificationRecipients',
                    ],
                ],
                $context,
            );
            $this->entityManager->create(FlowDefinition::class, $createPayload, $context);
        });
    }

    public function uninstall(Context $context): void
    {
        // Delete all flow with the ReorderMailEvent as a trigger
        $this->entityManager->deleteByCriteria(
            FlowDefinition::class,
            ['eventName' => ReorderMailEvent::EVENT_NAME],
            $context,
        );
    }
}
