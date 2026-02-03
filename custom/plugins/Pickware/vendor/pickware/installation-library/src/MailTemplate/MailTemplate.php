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

class MailTemplate
{
    private string $technicalName;

    /**
     * @var MailTemplateTranslation[]
     */
    private array $translations;

    /**
     * A list of variables that are available in the mail template.
     *
     * The key is the name of the variable. The value may be the name of an entity or null if the variable's type is not
     * an entity.
     *
     * Note that this list is not a strict contract of which template variables are actually set when creating the mail.
     * It is currently only an assistance for editing the template in the administration (see
     * https://github.com/shopware/shopware/blob/eb7b60826e9b69ed1e20e1cdaed6f28c2da90957/src/Administration/Resources/app/administration/src/module/sw-mail-template/page/sw-mail-template-detail/index.js#L90-L107)
     *
     * @var string[]
     */
    private array $availableTemplateVariables;

    /**
     * @param MailTemplateTranslation[] $translations
     */
    public function __construct(
        string $technicalName,
        array $translations,
        array $availableTemplateVariables,
    ) {
        $this->technicalName = $technicalName;
        $this->translations = $translations;
        $this->availableTemplateVariables = $availableTemplateVariables;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getTranslations(): array
    {
        return $this->translations;
    }

    public function getMailTranslations(): array
    {
        $translations = [];
        foreach ($this->translations as $translation) {
            $translations[$translation->getLocaleCode()] = [
                'subject' => $translation->getSubject(),
                'senderName' => $translation->getSender(), // Note that the entity property is 'senderName' here
                'description' => $translation->getDescription(),
                'contentHtml' => $translation->getContentHtml(),
                'contentPlain' => $translation->getContentPlain(),
            ];
        }

        return $translations;
    }

    public function getTypeNameTranslations(): array
    {
        $translations = [];
        foreach ($this->translations as $translation) {
            $translations[$translation->getLocaleCode()] = $translation->getTypeName();
        }

        return $translations;
    }

    public function getAvailableTemplateVariables(): array
    {
        return $this->availableTemplateVariables;
    }
}
