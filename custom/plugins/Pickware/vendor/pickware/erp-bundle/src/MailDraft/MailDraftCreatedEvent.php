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
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Contracts\EventDispatcher\Event;

class MailDraftCreatedEvent extends Event implements ShopwareEvent
{
    public const EVENT_NAME = 'pickware_erp.mail_draft.mail_draft_created';

    private Context $context;
    private MailDraft $mailDraft;

    public function __construct(MailDraft $mailDraft, Context $context)
    {
        $this->mailDraft = $mailDraft;
        $this->context = $context;
    }

    public function getMailDraft(): MailDraft
    {
        return $this->mailDraft;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
