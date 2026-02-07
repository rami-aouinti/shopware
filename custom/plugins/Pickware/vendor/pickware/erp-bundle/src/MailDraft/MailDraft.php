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

use InvalidArgumentException;
use JsonSerializable;
use Soundasleep\Html2Text;
use Throwable;

/**
 * Struct class to draft a mail in the Administration that will later be turned into an actual email with attachments.
 */
class MailDraft implements JsonSerializable
{
    private string $senderName = '';
    private string $senderMail = '';
    private array $recipients = [];
    private array $recipientsBcc = [];
    private string $subject = '';
    private string $contentHtml = '';
    private array $attachments = [];

    public function jsonSerialize(): array
    {
        return [
            'recipients' => $this->getRecipients(),
            'recipientsBcc' => $this->getRecipientsBcc(),
            'senderMail' => $this->getSenderEmailAddress(),
            'senderName' => $this->getSenderName(),
            'contentHtml' => $this->getContentHtml(),
            'subject' => $this->getSubject(),
            'attachments' => $this->getAttachments(),
        ];
    }

    public static function fromArray(array $array): self
    {
        $self = new self();

        $optionalParameters = [
            'attachments',
            'subject',
            'recipientsBcc',
            'contentHtml',
        ];
        foreach (array_keys(get_object_vars($self)) as $key) {
            if (!in_array($key, $optionalParameters) && !isset($array[$key])) {
                throw new InvalidArgumentException(sprintf('Property "%s" is missing in the mail draft', $key));
            }
        }

        $self->setRecipients($array['recipients']);
        $self->setSenderMail($array['senderMail']);
        $self->setSenderName($array['senderName']);
        $self->setContentHtml($array['contentHtml'] ?? '');

        if (isset($array['subject'])) {
            $self->setSubject($array['subject']);
        }
        if (isset($array['attachments'])) {
            // Attachments must be of type array and will later be hydrated in MailAttachmentFactory
            foreach ($array['attachments'] as $attachment) {
                if (!is_array($attachment)) {
                    throw new InvalidArgumentException('Mail attachments must be of type Array');
                }
            }
            $self->setAttachments($array['attachments']);
        }
        if (isset($array['recipientsBcc'])) {
            $self->setRecipientsBcc($array['recipientsBcc']);
        }

        return $self;
    }

    public function getSenderName(): string
    {
        return $this->senderName;
    }

    public function setSenderName(string $senderName): void
    {
        $this->senderName = $senderName;
    }

    public function getSenderEmailAddress(): string
    {
        return $this->senderMail;
    }

    public function setSenderMail(string $senderMail): void
    {
        $this->senderMail = $senderMail;
    }

    public function getRecipients(): array
    {
        return $this->recipients;
    }

    public function setRecipients(array $recipients): void
    {
        $this->recipients = $recipients;
    }

    public function getRecipientsBcc(): array
    {
        return $this->recipientsBcc;
    }

    public function setRecipientsBcc(array $recipientsBcc): void
    {
        $this->recipientsBcc = $recipientsBcc;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): void
    {
        $this->subject = $subject;
    }

    public function getContentHtml(): string
    {
        return $this->contentHtml;
    }

    public function setContentHtml(string $contentHtml): void
    {
        $this->contentHtml = $contentHtml;
    }

    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function setAttachments(array $attachments): void
    {
        $this->attachments = $attachments;
    }

    /**
     * Returns the content (plain and HTML) of the mail.
     */
    public function getContents(): array
    {
        try {
            $plainContent = Html2Text::convert($this->contentHtml);
        } catch (Throwable $e) {
            throw MailDraftException::htmlParsingFailed($e);
        }

        return [
            'text/plain' => $plainContent,
            'text/html' => $this->getContentHtml(),
        ];
    }

    /**
     * Shopware's mailer expects the sender to be formatted as [ mail-address => name ].
     */
    public function getCombinedSender(): array
    {
        return [$this->getSenderEmailAddress() => $this->getSenderName()];
    }

    /**
     * Shopware's mailer expects the recipients to be formatted as [ mail-address => name, .. ].
     */
    public function getCombinedRecipients(): array
    {
        $combinedRecipients = [];
        foreach ($this->getRecipients() as $recipient) {
            $combinedRecipients[$recipient] = $recipient;
        }

        return $combinedRecipients;
    }

    /**
     * Shopware's mailer expects the recipients to be formatted as [ mail-address => name, .. ].
     */
    public function getCombinedRecipientsBcc(): array
    {
        $combinedRecipientsBcc = [];
        foreach ($this->getRecipientsBcc() as $recipientBcc) {
            $combinedRecipientsBcc[$recipientBcc] = $recipientBcc;
        }

        return $combinedRecipientsBcc;
    }

    /**
     * Returns an array with additional data that can be passed to the MailFactory of shopify to create an Email.
     */
    public function getAdditionalData(): array
    {
        $additionalData = $this->jsonSerialize();
        // There is a bug in shopware that expects the wrong format of recipients in BCC and with that makes it impossible
        // to send BCC e-mails to multiple recipients (see also https://github.com/shopware/shopware/issues/2862).
        // ('recipientsBcc' => 'recipient@mail.com' instead of 'recipientsBcc' => ['recipient1@mail.com', 'recipient2@mail.com])
        // We therefore do not pass the BCC recipients ourselves but manually set them otherwise.
        unset($additionalData['recipientsBcc']);

        return $additionalData;
    }
}
