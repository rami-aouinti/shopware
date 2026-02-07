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

use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('pickware_erp.mail_template_content_generator')]
interface MailTemplateContentGenerator
{
    /**
     * Generates content for a specific mail template using the given options.
     *
     * @return array the generated content for the template
     * @throws MailDraftException
     */
    public function generateContent(Context $context, array $options = []): array;

    /**
     * Returns the technical name of the mail template type this generator can generate content for.
     *
     * @return string the technical name
     */
    public function getMailTemplateTypeTechnicalName(): string;
}
