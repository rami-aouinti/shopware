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

use Doctrine\DBAL\Connection;

class MailTemplateUpdater
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Updates all mail template translations (considers all templates and both plain and html content) by search and
     * replace a given string.
     */
    public function replaceStringInContentsOfAllMailTemplates(string $search, string $replace): void
    {
        $mailTemplateTranslations = $this->db->fetchAllAssociative(
            sprintf(
                'SELECT
                    mail_template_id,
                    language_id,
                    content_plain,
                    content_html
                FROM mail_template_translation
                WHERE content_plain LIKE "%%%s%%"
                OR content_html LIKE "%%%s%%";',
                $search,
                $search,
            ),
        );

        foreach ($mailTemplateTranslations as $mailTemplateTranslation) {
            $this->db->executeStatement(
                'UPDATE
                    mail_template_translation
                SET
                    content_plain = :contentPlain,
                    content_html = :contentHtml
                WHERE mail_template_id = :mailTemplateId
                AND language_id = :languageId;',
                [
                    'mailTemplateId' => $mailTemplateTranslation['mail_template_id'],
                    'languageId' => $mailTemplateTranslation['language_id'],
                    'contentPlain' => str_replace($search, $replace, $mailTemplateTranslation['content_plain']),
                    'contentHtml' => str_replace($search, $replace, $mailTemplateTranslation['content_html']),
                ],
            );
        }
    }
}
