<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Flow;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Customer\CustomerEmailService;
use Pickware\PickwareErpStarter\InvoiceCorrection\InvoiceCorrectionDocumentType;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\StornoRenderer;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Event\OrderAware;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

#[AsDecorator(SendMailAction::class)]
class SendMailActionDecorator extends FlowAction
{
    public function __construct(
        #[AutowireDecorated]
        private readonly FlowAction $decorated,
        private readonly CustomerEmailService $customerEmailService,
        private readonly EntityManager $entityManager,
    ) {}

    public static function getName(): string
    {
        return SendMailAction::getName();
    }

    /**
     * @return array<string>
     */
    public function requirements(): array
    {
        return $this->decorated->requirements();
    }

    public function handleFlow(StorableFlow $flow): void
    {
        $eventConfig = $flow->getConfig();

        if (!$flow->hasData(OrderAware::ORDER_ID) || !isset($eventConfig['recipient']['type']) || $eventConfig['recipient']['type'] !== 'default') {
            $this->decorated->handleFlow($flow);

            return;
        }

        /** @var OrderEntity $order */
        $order = $flow->getData('order');
        $criteria = new Criteria();

        /** @var array<string> $documentIds */
        $documentIds = $flow->getContext()->getExtension(SendMailAction::MAIL_CONFIG_EXTENSION)?->getDocumentIds() ?? [];
        /** @var array<string> $documentTypeIds */
        $documentTypeIds = $eventConfig['documentTypeIds'] ?? [];

        if (count($documentIds) === 0 && count($documentTypeIds) === 0) {
            // If no document ids or document type ids are provided, we do not need to fetch any documents.
            $this->decorated->handleFlow($flow);

            return;
        }

        if (count($documentIds) > 0) {
            $criteria->addFilter(new EqualsAnyFilter('id', $documentIds));
        }

        if (count($documentTypeIds) > 0) {
            $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('orderId', $order->getId()),
                new EqualsAnyFilter('documentTypeId', $documentTypeIds),
            ]));
        }

        $criteria->addFilter(new EqualsAnyFilter('documentType.technicalName', [
            InvoiceRenderer::TYPE,
            StornoRenderer::TYPE,
            InvoiceCorrectionDocumentType::TECHNICAL_NAME,
        ]));

        /** @var array<string> $documents */
        $documents = $this->entityManager->findIdsBy(
            DocumentDefinition::class,
            $criteria,
            $flow->getContext(),
        );

        if ($order && count($documents) > 0) {
            $emailAddress = $this->customerEmailService->getEmailAddressForInvoiceDocuments($order->getId(), $flow->getContext());

            if ($emailAddress) {
                $eventConfig['recipient'] = [
                    'type' => 'custom',
                    'data' => [$emailAddress => $order->getOrderCustomer()?->getFirstName() . ' ' . $order->getOrderCustomer()?->getLastName()],
                ];
                $flow->setConfig($eventConfig);
            }
        }

        $this->decorated->handleFlow($flow);
    }
}
