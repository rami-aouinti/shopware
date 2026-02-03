<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\AustrianPostBundle\ReturnLabel;

use Pickware\InstallationLibrary\MailTemplate\MailTemplate;
use Pickware\InstallationLibrary\MailTemplate\MailTemplateTranslation;

class ReturnLabelMailTemplate extends MailTemplate
{
    public const TECHNICAL_NAME = 'pickware_austrian_post_bundle_return_label';

    public function __construct()
    {
        parent::__construct(
            technicalName: self::TECHNICAL_NAME,
            translations: MailTemplateTranslation::loadFromDir(__DIR__ . '/Resources/mail-templates/return-label'),
            availableTemplateVariables: [
                'order' => 'order',
                'shopName' => null,
                'returnLabelDocuments' => null,
            ],
        );
    }
}
