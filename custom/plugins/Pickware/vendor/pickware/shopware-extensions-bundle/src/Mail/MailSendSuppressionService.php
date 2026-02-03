<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\Mail;

use Closure;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class MailSendSuppressionService
{
    private SystemConfigService $configService;

    public function __construct(SystemConfigService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * We can not suppress this via the SendMailAction::MAIL_CONFIG_EXTENSION, because Shopware creates a new context
     * for this flow and does not copy any extensions that have been set before into the new context.
     *
     * To temporarily suppress the mail sending you can use this helper method.
     *
     * @see SystemConfigServiceDecorator
     */
    public function runWithMailSendDisabled(Closure $closure, Context $context)
    {
        $originalMailConfigExtension = $context->getExtension(SendMailAction::MAIL_CONFIG_EXTENSION);
        $context->addExtension(SendMailAction::MAIL_CONFIG_EXTENSION, new MailSendSubscriberConfig(true));

        // Disable mail delivery with the pickware custom key to avoid unwanted side effects on concurrent requests
        $originalConfigValue = $this->configService->getBool(SystemConfigServiceDecorator::PICKWARE_DISABLE_MAIL_DELIVERY);
        $this->configService->set(SystemConfigServiceDecorator::PICKWARE_DISABLE_MAIL_DELIVERY, true);

        try {
            return $closure();
        } finally {
            // Reset context and mail delivery settings
            $this->configService->set(SystemConfigServiceDecorator::PICKWARE_DISABLE_MAIL_DELIVERY, $originalConfigValue);
            $context->removeExtension(SendMailAction::MAIL_CONFIG_EXTENSION);
            if ($originalMailConfigExtension !== null) {
                $context->addExtension(SendMailAction::MAIL_CONFIG_EXTENSION, $originalMailConfigExtension);
            }
        }
    }
}
