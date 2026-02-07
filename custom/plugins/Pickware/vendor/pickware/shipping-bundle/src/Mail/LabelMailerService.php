<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Mail;

use League\Flysystem\FilesystemOperator;
use Pickware\DalBundle\ContextFactory;
use Pickware\DalBundle\EntityManager;
use Pickware\DocumentBundle\Document\Model\DocumentEntity;
use Pickware\ShippingBundle\Installation\Documents\ReturnLabelDocumentType;
use Pickware\ShippingBundle\Shipment\Model\ShipmentDefinition;
use Pickware\ShippingBundle\Shipment\Model\ShipmentEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class LabelMailerService
{
    private const SHOP_NAME_CONFIG_KEY = 'core.basicInformation.shopName';

    private EntityManager $entityManager;
    private AbstractMailService $shopwareMailService;
    private SystemConfigService $systemConfigService;
    private ContextFactory $contextFactory;
    private FilesystemOperator $documentBundleFileSystem;

    public function __construct(
        EntityManager $entityManager,
        AbstractMailService $shopwareMailService,
        SystemConfigService $systemConfigService,
        ContextFactory $contextFactory,
        FilesystemOperator $documentBundleFileSystem,
    ) {
        $this->entityManager = $entityManager;
        $this->shopwareMailService = $shopwareMailService;
        $this->systemConfigService = $systemConfigService;
        $this->contextFactory = $contextFactory;
        $this->documentBundleFileSystem = $documentBundleFileSystem;
    }

    public function sendReturnLabelsOfShipmentToOrderCustomer(
        string $shipmentId,
        string $orderId,
        Context $context,
    ): void {
        /** @var ShipmentEntity $shipment */
        $shipment = $this->entityManager->findByPrimaryKey(ShipmentDefinition::class, $shipmentId, $context, [
            'documents',
            'carrier',
        ]);
        if (!$shipment) {
            throw LabelMailerException::shipmentNotFound($shipmentId);
        }

        $returnLabelDocuments = $shipment->getDocuments()->filterByProperty(
            'documentTypeTechnicalName',
            ReturnLabelDocumentType::TECHNICAL_NAME,
        );
        if ($returnLabelDocuments->count() === 0) {
            throw LabelMailerException::shipmentHasNoReturnLabelDocuments($shipmentId);
        }

        // Retrieve mail template and order in correct language
        $orderContext = $this->contextFactory->deriveOrderContext($orderId, $context);
        $orderContext->setConsiderInheritance(true);

        /** @var MailTemplateCollection $returnLabelMailTemplates */
        $returnLabelMailTemplates = $this->entityManager->findBy(
            MailTemplateDefinition::class,
            ['mailTemplateType.pickwareShippingCarriersReturnLabel.technicalName' => $shipment->getCarrierTechnicalName()],
            $orderContext,
            ['mailTemplateType'],
        );
        $returnLabelMailTemplate = $returnLabelMailTemplates->first();
        if (!$returnLabelMailTemplate) {
            throw LabelMailerException::noMailTemplateConfiguredForCarrier(
                $shipment->getCarrier()->getName(),
            );
        }

        /** @var OrderEntity $order */
        $order = $this->entityManager->findByPrimaryKey(OrderDefinition::class, $orderId, $orderContext, [
            'orderCustomer.salutation',
        ]);
        $orderCustomer = $order->getOrderCustomer();
        $salesChannelId = $order->getSalesChannelId();
        $shopName = $this->systemConfigService->get(self::SHOP_NAME_CONFIG_KEY, $salesChannelId);

        $message = $this->shopwareMailService->send([
            'salesChannelId' => $salesChannelId,
            'recipients' => [
                $orderCustomer->getEmail() => sprintf(
                    '%s %s',
                    $orderCustomer->getFirstName(),
                    $orderCustomer->getLastName(),
                ),
            ],
            'contentHtml' => $returnLabelMailTemplate->getContentHtml(),
            'contentPlain' => $returnLabelMailTemplate->getContentPlain(),
            'subject' => $returnLabelMailTemplate->getSubject(),
            'senderName' => $returnLabelMailTemplate->getSenderName(),
            'binAttachments' => array_values($returnLabelDocuments->map(
                fn(DocumentEntity $document) => [
                    'content' => $this->documentBundleFileSystem->read($document->getPathInPrivateFileSystem()),
                    'fileName' => sprintf('return-label-%s.pdf', $order->getOrderNumber()),
                    'mimeType' => $document->getMimeType(),
                ],
            )),
        ], $context, [
            'order' => $order,
            'shopName' => $shopName,
            'returnLabelDocuments' => $returnLabelDocuments,
        ]);
        if ($message === null) {
            throw LabelMailerException::failedToRenderMailTemplate($returnLabelMailTemplate->getMailTemplateType()->getName());
        }
    }
}
