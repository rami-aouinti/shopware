<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\OrderDocument;

use Pickware\DalBundle\ContextFactory;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\InvoiceCorrection\InvoiceCorrectionDocumentType;
use Pickware\PickwareErpStarter\InvoiceCorrection\InvoiceCorrectionMailTemplate;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class OrderDocumentMailerService
{
    private const SHOP_NAME_CONFIG_KEY = 'core.basicInformation.shopName';

    private EntityManager $entityManager;
    private AbstractMailService $mailService;
    private SystemConfigService $systemConfigService;
    private ContextFactory $contextFactory;
    private DocumentGenerator $documentGenerator;

    public function __construct(
        EntityManager $entityManager,
        #[Autowire(service: 'Shopware\\Core\\Content\\Mail\\Service\\MailService')]
        AbstractMailService $mailService,
        SystemConfigService $systemConfigService,
        ContextFactory $contextFactory,
        DocumentGenerator $documentGenerator,
    ) {
        $this->entityManager = $entityManager;
        $this->mailService = $mailService;
        $this->systemConfigService = $systemConfigService;
        $this->contextFactory = $contextFactory;
        $this->documentGenerator = $documentGenerator;
    }

    public function sendDocumentToEmailAddress(string $documentId, string $emailAddress, Context $context): void
    {
        /** @var DocumentEntity $document */
        $document = $this->entityManager->getByPrimaryKey(DocumentDefinition::class, $documentId, $context, [
            'documentType',
            // The following association is necessary, so we can pass the document into the DocumentService
            'documentMediaFile',
        ]);

        $documentTypeTechnicalName = $document->getDocumentType()->getTechnicalName();
        switch ($documentTypeTechnicalName) {
            case InvoiceRenderer::TYPE:
                $mailTemplateTypeTechnicalName = MailTemplateTypes::MAILTYPE_DOCUMENT_INVOICE;
                $orderAssociations = ['orderCustomer.salutation'];
                break;
            case ReceiptDocumentType::TECHNICAL_NAME:
                $mailTemplateTypeTechnicalName = ReceiptMailTemplate::TECHNICAL_NAME;
                $orderAssociations = ReceiptMailTemplate::ORDER_ASSOCIATIONS;
                break;
            case CouponReceiptDocumentType::TECHNICAL_NAME:
                $mailTemplateTypeTechnicalName = CouponReceiptMailTemplate::TECHNICAL_NAME;
                $orderAssociations = CouponReceiptMailTemplate::ORDER_ASSOCIATIONS;
                break;
            case ReturnOrderReceiptDocumentType::TECHNICAL_NAME:
                $mailTemplateTypeTechnicalName = ReturnOrderReceiptMailTemplate::TECHNICAL_NAME;
                $orderAssociations = ReturnOrderReceiptMailTemplate::ORDER_ASSOCIATIONS;
                break;
            case InvoiceCorrectionDocumentType::TECHNICAL_NAME:
                $mailTemplateTypeTechnicalName = InvoiceCorrectionMailTemplate::TECHNICAL_NAME;
                $orderAssociations = InvoiceCorrectionMailTemplate::ORDER_ASSOCIATIONS;
                break;
            default:
                throw OrderDocumentMailerException::unsupportedDocumentType($documentTypeTechnicalName);
        }

        // Create derived order context to retrieve mail template and order in correct language, currency, etc.
        $orderContext = $this->contextFactory->deriveOrderContext($document->getOrderId(), $context);
        $orderContext->setConsiderInheritance(true);

        /** @var OrderEntity $order */
        $order = $this->entityManager->getByPrimaryKey(
            OrderDefinition::class,
            $document->getOrderId(),
            $orderContext,
            $orderAssociations,
        );

        $mailTemplates = $this->entityManager->findBy(MailTemplateDefinition::class, [
            'mailTemplateType.technicalName' => $mailTemplateTypeTechnicalName,
        ], $orderContext);
        $mailTemplate = $mailTemplates->first();
        if (!$mailTemplate) {
            throw OrderDocumentMailerException::noMailTemplateExistsForDocumentType($documentTypeTechnicalName);
        }

        $documentFile = $this->documentGenerator->readDocument($document->getId(), $orderContext);
        $shopName = $this->systemConfigService->get(self::SHOP_NAME_CONFIG_KEY, $order->getSalesChannelId());

        // Remove "pickware_pos_" namespacing from file names
        $filename = str_replace('pickware_pos_', '', $documentFile->getName());
        $message = $this->mailService->send(
            [
                'salesChannelId' => $order->getSalesChannelId(),
                'recipients' => [$emailAddress => ''],
                'contentHtml' => $mailTemplate->getContentHtml(),
                'contentPlain' => $mailTemplate->getContentPlain(),
                'subject' => $mailTemplate->getSubject(),
                'senderName' => $mailTemplate->getSenderName(),
                'binAttachments' => [
                    [
                        'content' => $documentFile->getContent(),
                        'fileName' => $filename,
                        'mimeType' => $documentFile->getContentType(),
                    ],
                ],
            ],
            $context,
            [
                'order' => $order,
                'shopName' => $shopName,
                'a11yDocuments' => [],
            ],
        );
        if ($message === null) {
            throw OrderDocumentMailerException::failedToRenderMailTemplate(
                $mailTemplate->getId(),
                $order->getId(),
                $mailTemplateTypeTechnicalName,
            );
        }

        $this->entityManager->update(
            DocumentDefinition::class,
            [
                [
                    'id' => $documentId,
                    'sent' => true,
                ],
            ],
            $context,
        );
    }
}
