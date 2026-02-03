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
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class MailTemplateContentGeneratorRegistry
{
    /**
     * @var MailTemplateContentGenerator[]
     */
    private array $mailTemplateContentGenerators = [];

    public function __construct(
        #[TaggedIterator('pickware_erp.mail_template_content_generator')]
        iterable $generators,
    ) {
        /** @var MailTemplateContentGenerator $generator */
        foreach ($generators as $generator) {
            if (!($generator instanceof MailTemplateContentGenerator)) {
                throw new InvalidArgumentException(
                    'Expected generator implement the interface MailTemplateContentGenerator.',
                );
            }
            $this->mailTemplateContentGenerators[$generator->getMailTemplateTypeTechnicalName()] = $generator;
        }
    }

    public function getGeneratorByTechnicalName(string $technicalName): ?MailTemplateContentGenerator
    {
        return $this->mailTemplateContentGenerators[$technicalName] ?? null;
    }
}
