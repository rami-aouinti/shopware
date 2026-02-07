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
use League\Flysystem\FilesystemOperator;
use Pickware\DalBundle\EntityManager;
use Pickware\DocumentBundle\Document\Model\DocumentDefinition;
use Pickware\DocumentBundle\Document\Model\DocumentEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class MailDraftAttachmentFactory
{
    private EntityManager $entityManager;
    private FilesystemOperator $documentBundleFileSystem;

    public function __construct(
        EntityManager $entityManager,
        #[Autowire(service: 'pickware_document_bundle.filesystem.private')]
        FilesystemOperator $documentBundleFileSystem,
    ) {
        $this->entityManager = $entityManager;
        $this->documentBundleFileSystem = $documentBundleFileSystem;
    }

    public function createAttachment(array $attachment, Context $context): MailDraftAttachment
    {
        if (!isset($attachment['type'])) {
            throw new InvalidArgumentException('A mail attachment needs a "type"');
        }

        if ($attachment['type'] === MailDraftAttachment::TYPE_PICKWARE_DOCUMENT) {
            return $this->createAttachmentForPickwareDocument($attachment, $context);
        }

        if ($attachment['type'] === MailDraftAttachment::TYPE_PICKWARE_PDF_DOCUMENT) {
            return $this->createPdfAttachmentForPickwareDocument($attachment, $context);
        }

        throw new InvalidArgumentException(
            sprintf('Unknown mail attachment type "%s"', $attachment['type']),
        );
    }

    private function createAttachmentForPickwareDocument(array $attachment, Context $context): MailDraftAttachment
    {
        if (!isset($attachment['documentId'])) {
            throw new InvalidArgumentException(
                sprintf(
                    'A mail attachment with type "%s" needs a "documentId"',
                    MailDraftAttachment::TYPE_PICKWARE_DOCUMENT,
                ),
            );
        }

        /** @var DocumentEntity|null $document */
        $document = $this->entityManager->findByPrimaryKey(
            DocumentDefinition::class,
            $attachment['documentId'],
            $context,
        );

        if (!$document) {
            throw MailDraftException::attachmentDocumentNotFound($attachment['documentId']);
        }

        if (!$this->documentBundleFileSystem->fileExists($document->getPathInPrivateFileSystem())) {
            throw MailDraftException::attachmentFileNotFound($document->getFileName());
        }

        $content = $this->documentBundleFileSystem->read($document->getPathInPrivateFileSystem());

        return MailDraftAttachment::fromArray([
            'fileName' => $document->getFileName(),
            'mimeType' => $document->getMimeType(),
            'content' => $content,
        ]);
    }

    private function createPdfAttachmentForPickwareDocument(array $attachment, Context $context): MailDraftAttachment
    {
        if (!isset($attachment['content'])) {
            throw new InvalidArgumentException(
                sprintf(
                    'A mail attachment with type "%s" needs content',
                    MailDraftAttachment::TYPE_PICKWARE_PDF_DOCUMENT,
                ),
            );
        }

        $content = base64_decode($attachment['content']);

        return MailDraftAttachment::fromArray([
            'fileName' => $attachment['fileName'],
            'mimeType' => 'application/pdf',
            'content' => $content,
        ]);
    }
}
