<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\MailDraft\DependencyInjection;

use InvalidArgumentException;
use Pickware\PickwareErpStarter\MailDraft\MailTemplateContentGenerator;
use Pickware\PickwareErpStarter\MailDraft\MailTemplateSenderMailProvider;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class MailTemplateSenderMailProviderRegistry
{
    /**
     * @var MailTemplateSenderMailProvider[]
     */
    private array $mailTemplateSenderMailProviders = [];

    public function __construct(
        #[TaggedIterator('pickware_erp.mail_template_sender_mail_provider')]
        iterable $providers,
    ) {
        /** @var MailTemplateContentGenerator $provider */
        foreach ($providers as $provider) {
            if (!($provider instanceof MailTemplateSenderMailProvider)) {
                throw new InvalidArgumentException(sprintf(
                    'Expected the provider %s to implement the interface MailTemplateSenderMailProvider.',
                    $provider::class,
                ));
            }
            $this->mailTemplateSenderMailProviders[$provider->getMailTemplateTypeTechnicalName()] = $provider;
        }
    }

    public function getProviderByTemplateTechnicalName(string $technicalName): MailTemplateSenderMailProvider
    {
        return $this->mailTemplateSenderMailProviders[$technicalName] ?? $this->mailTemplateSenderMailProviders[null];
    }
}
