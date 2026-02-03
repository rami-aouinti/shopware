<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picklist\Renderer;

use DateTime;
use Doctrine\DBAL\Connection;
use Exception;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\CustomField\DocumentCustomFieldService;
use Pickware\PickwareErpStarter\Picklist\PicklistCustomProductGenerator;
use Pickware\PickwareErpStarter\Picklist\PicklistDocumentType;
use Pickware\PickwareErpStarter\Picklist\PicklistGenerator;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\ShopwareExtensionsBundle\OrderDocument\OrderDocumentRenderer;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\OrderDocumentCriteriaFactory;
use Shopware\Core\Checkout\Document\Renderer\RendererResult;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class PicklistDocumentRenderer extends AbstractDocumentRenderer
{
    public const DEFAULT_TEMPLATE = '@PickwareErpBundle/documents/picklist.html.twig';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $rootDir,
        private readonly EntityManager $entityManager,
        private readonly PicklistGenerator $picklistGenerator,
        private readonly PicklistCustomProductGenerator $picklistCustomProductGenerator,
        private readonly DocumentTemplateRenderer $documentTemplateRenderer,
        private readonly PicklistDocumentContentGenerator $contentGenerator,
        private readonly NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        private readonly Connection $connection,
        private readonly OrderDocumentRenderer $orderDocumentRenderer,
        private readonly DocumentCustomFieldService $documentCustomFieldService,
    ) {}

    public function render(array $operations, Context $context, DocumentRendererConfig $rendererConfig): RendererResult
    {
        $result = new RendererResult();
        $orderIds = array_map(fn(DocumentGenerateOperation $operation) => $operation->getOrderId(), $operations);
        if (empty($orderIds)) {
            return $result;
        }

        $warehousesById = [];
        $orderIdsByLanguageId = $this->getOrdersLanguageId(array_values($orderIds), $context->getVersionId(), $this->connection);
        foreach ($orderIdsByLanguageId as ['language_id' => $languageId, 'ids' => $orderIds]) {
            // Assigns the corresponding of the orderId to the languageIdChain. If there is no languageId given it sets
            // default languageId of the context. It filters the array to unique values, so that there are no duplicates
            // of languageIds. Used for rendering process by shopware
            $context = $context->assign([
                'languageIdChain' => array_unique(array_filter([$languageId, $context->getLanguageId()])),
            ]);
            $criteria = OrderDocumentCriteriaFactory::create(explode(',', $orderIds), $rendererConfig->deepLinkCode);
            $criteria->addSorting(new FieldSorting('orderNumber'));
            /** @var OrderCollection $orders */
            $orders = $this->entityManager->findBy(
                OrderDefinition::class,
                $criteria,
                $context,
                // Load associations required on the document template
                ['salesChannel'],
            );
            foreach ($orders as $order) {
                try {
                    $operation = $operations[$order->getId()];
                    $warehouseId = $operation->getConfig()['warehouseId'];
                    $config = $this->createDocumentConfiguration(PicklistDocumentType::TECHNICAL_NAME, $order->getSalesChannelId(), $context);
                    $number = $operation->getConfig()['documentNumber'] ?? $this->getNextPicklistDocumentNumber($context, $order, $operation);
                    $date = $operation->getConfig()['documentDate'] ?? (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

                    if (!array_key_exists($warehouseId, $warehousesById)) {
                        $warehousesById[$warehouseId] = $this->entityManager->getByPrimaryKey(
                            WarehouseDefinition::class,
                            $warehouseId,
                            $context,
                        );
                    }
                    $warehouse = $warehousesById[$warehouseId];

                    $config->merge([
                        ...$operation->getConfig(),
                        'documentNumber' => $number,
                        'orderNumber' => $order->getOrderNumber(),
                        'warehouseId' => $warehouseId,
                        'documentDate' => $date,
                    ]);

                    $customFieldsConfig = $this->documentCustomFieldService->getCustomFieldsConfig($order->getId(), PicklistDocumentType::TECHNICAL_NAME, $context);
                    $config->assign($customFieldsConfig);

                    $html = $this->documentTemplateRenderer->render(
                        self::DEFAULT_TEMPLATE,
                        [
                            'order' => $order,
                            'warehouse' => $warehouse,
                            'pickingRouteNodes' => $this->contentGenerator->createDocumentPickingRouteNodes(
                                $this->picklistGenerator->generatePicklist($order->getId(), $warehouseId, $context),
                                $order->getLineItems()->getIds(),
                                $context,
                            ),
                            'customProducts' => $this->picklistCustomProductGenerator->generatorCustomProductDefinitions(
                                $order->getLineItems(),
                            ),
                            'config' => $config,
                            'rootDir' => $this->rootDir,
                            'context' => $context,
                        ],
                        $context,
                        $order->getSalesChannelId(),
                        $order->getLanguageId(),
                        $order->getLanguage()->getLocale()->getCode(),
                    );

                    $renderedDocument = $this->orderDocumentRenderer->createRenderedDocument(
                        number: $number,
                        name: $config->buildName(),
                        fileExtension: $operation->getFileType(),
                        config: $config->jsonSerialize(),
                        html: $html,
                    );

                    $result->addSuccess($order->getId(), $renderedDocument);
                } catch (Exception $error) {
                    $result->addError($order->getId(), $error);
                }
            }
        }

        return $result;
    }

    private function getNextPicklistDocumentNumber(Context $context, OrderEntity $order, DocumentGenerateOperation $operation): string
    {
        return $this->numberRangeValueGenerator->getValue(
            'document_' . PicklistDocumentType::TECHNICAL_NAME,
            $context,
            $order->getSalesChannelId(),
            $operation->isPreview(),
        );
    }

    public function getDecorated(): AbstractDocumentRenderer
    {
        throw new DecorationPatternException(self::class);
    }

    public function supports(): string
    {
        return PicklistDocumentType::TECHNICAL_NAME;
    }

    private function createDocumentConfiguration(string $documentType, string $salesChannelId, Context $context): DocumentConfiguration
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('documentType.technicalName', $documentType));
        $criteria->addAssociation('logo');
        $criteria->getAssociation('salesChannels')->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $criteria->addFilter(new EqualsFilter('global', true));
        /** @var DocumentBaseConfigEntity $documentConfigs */
        $documentConfigs = $this->entityManager->findFirstBy(DocumentBaseConfigDefinition::class, new FieldSorting('createdAt', FieldSorting::ASCENDING), $context, $criteria);

        $salesChannelConfig = $documentConfigs->getSalesChannels()->first();

        return DocumentConfigurationFactory::createConfiguration([], $documentConfigs, $salesChannelConfig);
    }
}
