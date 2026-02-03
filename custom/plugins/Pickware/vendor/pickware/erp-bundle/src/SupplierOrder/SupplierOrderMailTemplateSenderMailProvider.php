<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder;

use Pickware\PickwareErpStarter\MailDraft\DefaultSenderMailProvider;
use Pickware\PickwareErpStarter\MailDraft\MailTemplateSenderMailProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SupplierOrderMailTemplateSenderMailProvider implements MailTemplateSenderMailProvider
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        #[Autowire(service: DefaultSenderMailProvider::class)]
        private readonly MailTemplateSenderMailProvider $defaultSenderMailProvider,
    ) {}

    public function getSenderEmailAddress(): string
    {
        $alternativeDefaultSenderEmailAddress = $this->systemConfigService->get('PickwareErpBundle.global-plugin-config.supplierOrderSenderEmailAddress');

        if ($alternativeDefaultSenderEmailAddress === null || $alternativeDefaultSenderEmailAddress === '') {
            return $this->defaultSenderMailProvider->getSenderEmailAddress();
        }

        return $alternativeDefaultSenderEmailAddress;
    }

    public function getMailTemplateTypeTechnicalName(): string
    {
        return SupplierOrderMailTemplate::MAIL_TEMPLATE_TYPE_TECHNICAL_NAME;
    }
}
