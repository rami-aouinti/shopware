<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\MailDraft;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('pickware_erp.mail_template_sender_mail_provider')]
interface MailTemplateSenderMailProvider
{
    /**
     * Provides the sender email address that should be used when sending this mail template.
     *
     * @return string the sender email address to use for the mail template
     */
    public function getSenderEmailAddress(): string;

    /**
     * Returns the technical name of the mail template type where the email from this provider may be used.
     *
     * @return ?string the technical name
     */
    public function getMailTemplateTypeTechnicalName(): ?string;
}
