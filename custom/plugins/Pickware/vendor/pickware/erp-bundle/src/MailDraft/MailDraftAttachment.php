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

class MailDraftAttachment implements JsonSerializable
{
    public const TYPE_PICKWARE_DOCUMENT = 'pickware_document';
    public const TYPE_PICKWARE_PDF_DOCUMENT = 'pickware_document_pdf';

    private string $fileName = '';
    private string $content = '';
    private string $mimeType = '';

    public function jsonSerialize(): array
    {
        return [
            'fileName' => $this->getFileName(),
            'content' => $this->getContent(),
            'mimeType' => $this->getMimeType(),
        ];
    }

    public static function fromArray(array $array): self
    {
        $self = new self();

        foreach (array_keys(get_object_vars($self)) as $key) {
            if (!isset($array[$key])) {
                throw new InvalidArgumentException(sprintf('Property "%s" is missing in the mail attachment', $key));
            }
        }

        $self->setFileName($array['fileName']);
        $self->setContent($array['content']);
        $self->setMimeType($array['mimeType']);

        return $self;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): void
    {
        $this->fileName = $fileName;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }
}
