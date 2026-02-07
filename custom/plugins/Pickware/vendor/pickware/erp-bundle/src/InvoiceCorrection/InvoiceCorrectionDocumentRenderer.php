<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\InvoiceCorrection;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\InvoiceCorrection\Events\InvoiceCorrectionOrderEvent;
use Pickware\PickwareErpStarter\OrderCalculation\CalculatableOrder;
use Pickware\PickwareErpStarter\OrderCalculation\CalculatableOrderLineItem;
use Pickware\ShopwareExtensionsBundle\OrderDocument\OrderDocumentRenderer;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\RendererResult;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;

class InvoiceCorrectionDocumentRenderer extends AbstractDocumentRenderer
{
    public const DEFAULT_TEMPLATE = '@PickwareErpBundle/documents/invoice-correction.html.twig';
    public const DOCUMENT_CONFIGURATION_REFERENCED_DOCUMENT_ID_KEY = 'pickwareErpReferencedDocumentId';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $rootDir,
        private readonly EntityManager $entityManager,
        private readonly InvoiceCorrectionCalculator $invoiceCorrectionCalculator,
        private readonly InvoiceCorrectionConfigGenerator $invoiceCorrectionConfigGenerator,
        private readonly DocumentTemplateRenderer $documentTemplateRenderer,
        private readonly NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly OrderDocumentRenderer $orderDocumentRenderer,
        private readonly ClockInterface $clock,
        private readonly DocumentConfigLoader $documentConfigLoader,
    ) {}

    public function supports(): string
    {
        return InvoiceCorrectionDocumentType::TECHNICAL_NAME;
    }

    /**
     * @param array<string,DocumentGenerateOperation> $operations
     */
    public function render(array $operations, Context $context, DocumentRendererConfig $rendererConfig): RendererResult
    {
        $result = new RendererResult();
        $orderIds = array_map(fn(DocumentGenerateOperation $operation) => $operation->getOrderId(), $operations);
        if (empty($orderIds)) {
            return $result;
        }
        $orderIdsByLanguageId = $this->getOrdersLanguageId(array_values($orderIds), $context->getVersionId(), $this->connection);
        foreach ($orderIdsByLanguageId as ['language_id' => $languageId, 'ids' => $orderIds]) {
            // Assigns the corresponding of the orderId to the languageIdChain. If there is no languageId given it sets
            // default languageId of the context. It filters the array to unique values, so that there are no duplicates
            // of languageIds. Used for rendering process by shopware
            $context = $context->assign([
                'languageIdChain' => array_unique(array_filter([$languageId, $context->getLanguageId()])),
            ]);

            foreach (explode(',', $orderIds) as $orderId) {
                try {
                    $operation = $operations[$orderId];
                    if ($operation->getReferencedDocumentId()) {
                        throw InvoiceCorrectionException::invalidDocumentConfiguration(sprintf(
                            'A document of type "%s" must not reference another document directly in the "referencedDocumentId"'
                            . ' property.',
                            InvoiceCorrectionDocumentType::TECHNICAL_NAME,
                        ));
                    }

                    // create version of order to ensure the document stays the same even if the order changes
                    $operation->setOrderVersionId($this->entityManager->createVersion(
                        OrderDefinition::class,
                        $orderId,
                        $context,
                        'document',
                    ));

                    if ($operation->getDocumentId() !== null) {
                        $referencedDocumentConfiguration = $this->invoiceCorrectionConfigGenerator->getReferencedDocumentConfigurationForExistingInvoiceCorrection($operation->getDocumentId(), $context);
                    } else {
                        $referencedDocumentConfiguration = $this->invoiceCorrectionConfigGenerator->getReferencedDocumentConfiguration($orderId, $context);
                    }
                    $invoiceCorrection = $this->invoiceCorrectionCalculator->calculateInvoiceCorrection(
                        $orderId,
                        $referencedDocumentConfiguration[self::DOCUMENT_CONFIGURATION_REFERENCED_DOCUMENT_ID_KEY],
                        $operation->getOrderVersionId(),
                        $context,
                    );

                    /** @var OrderEntity $order */
                    $order = $this->entityManager->getByPrimaryKey(
                        OrderDefinition::class,
                        $orderId,
                        $context->createWithVersionId($operation->getOrderVersionId()),
                        [
                            // Shopware default associations for the default order document template and possible
                            // template variable subscribers (do not remove). See also:
                            // Shopware\Core\Checkout\Document\Event\DocumentTemplateRendererParameterEvent
                            'lineItems',
                            'transactions.paymentMethod',
                            'currency',
                            'language.locale',
                            'addresses.country',
                            'deliveries.positions',
                            'deliveries.shippingMethod',
                            'orderCustomer.customer',
                            'deliveries.shippingOrderAddress.country',
                        ],
                    );

                    // Clone is required because Shopware's DocumentConfigLoader caches the configuration object.
                    // Without cloning, merge() would mutate the cached object and affect subsequent documents.
                    $config = clone $this->documentConfigLoader->load(
                        InvoiceCorrectionDocumentType::TECHNICAL_NAME,
                        $order->getSalesChannelId(),
                        $context,
                    );

                    $config->merge($operation->getConfig());
                    $config->assign([
                        'custom' => array_merge(
                            $config->custom,
                            $referencedDocumentConfiguration,
                        ),
                    ]);

                    $config->merge([
                        'intraCommunityDelivery' => $this->isAllowIntraCommunityDelivery(
                            $config->jsonSerialize(),
                            $order,
                        ),
                    ]);

                    $number = $config->getDocumentNumber() ?: $this->getNextInvoiceCorrectionDocumentNumber($context, $order, $operation);
                    $config->assign(['documentNumber' => $number]);
                    $documentDate = $config->getDocumentDate() ?: $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
                    $config->assign(['documentDate' => $documentDate]);
                    $this->applyInvoiceCorrectionToOrder($order, $invoiceCorrection);
                    $this->dispatcher->dispatch(new InvoiceCorrectionOrderEvent($order, $context));

                    $html = $this->documentTemplateRenderer->render(
                        self::DEFAULT_TEMPLATE,
                        [
                            'invoiceCorrection' => $order,
                            'config' => $config,
                            'rootDir' => $this->rootDir,
                            'context' => $context,
                            // Shopware default order parameter with associations for the default order document
                            // template and possible template variable subscribers (do not remove). See also:
                            // Shopware\Core\Checkout\Document\Event\DocumentTemplateRendererParameterEvent
                            'order' => $order,
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
                } catch (Throwable $error) {
                    $result->addError($orderId, $error);
                }
            }
        }

        return $result;
    }

    private function getNextInvoiceCorrectionDocumentNumber(Context $context, OrderEntity $order, DocumentGenerateOperation $operation): string
    {
        return $this->numberRangeValueGenerator->getValue(
            InvoiceCorrectionNumberRange::TECHNICAL_NAME,
            $context,
            $order->getSalesChannelId(),
            $operation->isPreview(),
        );
    }

    public function getDecorated(): AbstractDocumentRenderer
    {
        throw new DecorationPatternException(self::class);
    }

    private function applyInvoiceCorrectionToOrder(
        OrderEntity $orderEntity,
        CalculatableOrder $invoiceCorrection,
    ): void {
        $orderEntity->setPrice($invoiceCorrection->price);
        $orderEntity->setAmountNet($invoiceCorrection->price->getNetPrice());
        $orderEntity->setAmountTotal($invoiceCorrection->price->getTotalPrice());
        $orderEntity->setPositionPrice($invoiceCorrection->price->getPositionPrice());
        $orderEntity->setTaxStatus($invoiceCorrection->price->getTaxStatus());
        $orderEntity->setShippingTotal($invoiceCorrection->shippingCosts->getTotalPrice());
        $orderEntity->setShippingCosts($invoiceCorrection->shippingCosts);
        $orderEntity->setLineItems(new OrderLineItemCollection(array_map(
            fn(CalculatableOrderLineItem $orderLineItem) => $this->transformOrderLineItemToOrderLineItemEntity($orderLineItem, $orderEntity->getId()),
            ImmutableCollection::create($invoiceCorrection->lineItems)
                ->sorted(fn(CalculatableOrderLineItem $a, CalculatableOrderLineItem $b) => $a->position <=> $b->position)
                ->asArray(),
        )));
    }

    private function transformOrderLineItemToOrderLineItemEntity(
        CalculatableOrderLineItem $orderLineItem,
        string $orderId,
    ): OrderLineItemEntity {
        $orderLineItemEntity = new OrderLineItemEntity();
        // Set some required properties with default values
        $orderLineItemEntity->setId(Uuid::randomHex());
        $orderLineItemEntity->setPosition(0);
        $orderLineItemEntity->setGood(false);
        $orderLineItemEntity->setStackable(false);
        $orderLineItemEntity->setRemovable(false);

        $orderLineItemEntity->setOrderId($orderId);
        $orderLineItemEntity->setQuantity($orderLineItem->quantity);
        $orderLineItemEntity->setUnitPrice($orderLineItem->price->getUnitPrice());
        $orderLineItemEntity->setTotalPrice($orderLineItem->price->getTotalPrice());
        $orderLineItemEntity->setPrice($orderLineItem->price);
        $orderLineItemEntity->setLabel($orderLineItem->label);
        $orderLineItemEntity->setType($orderLineItem->type);
        $orderLineItemEntity->setProductId($orderLineItem->productId);
        $orderLineItemEntity->setReferencedId($orderLineItem->productId);
        $orderLineItemEntity->setIdentifier($orderLineItem->productId ?? Uuid::randomHex());
        $orderLineItemEntity->setPayload($orderLineItem->payload);

        return $orderLineItemEntity;
    }

    /**
     * Note: Must be protected. See https://github.com/shopware/shopware/commit/7ac9a82f570f05e771bc8fabb430f61517bdbd38#diff-7712db93de4c487c59e8ad067c1e30c3c656df9263a1179c41cc8bc1f640f260R49
     *
     * @param  array<string, mixed> $config
     */
    protected function isAllowIntraCommunityDelivery(array $config, OrderEntity $order): bool
    {
        if (empty($config['displayAdditionalNoteDelivery'])) {
            return false;
        }

        $orderDelivery = $order->getDeliveries()?->first();
        if (!$orderDelivery) {
            return false;
        }

        $shippingAddress = $orderDelivery->getShippingOrderAddress();
        $country = $shippingAddress?->getCountry();

        if ($country === null) {
            return false;
        }

        // If eu property does not exist, we need to fall back to the deliveryCountries config.
        if (!$country->has('isEu') && empty($config['deliveryCountries'])) {
            return false;
        }

        $isEu = $this->isEu($country, $config['deliveryCountries']);

        $customerType = $order->getOrderCustomer()?->getCustomer()?->getAccountType();
        if ($customerType !== CustomerEntity::ACCOUNT_TYPE_BUSINESS) {
            return false;
        }

        $isCompanyTaxFree = $country->getCompanyTax()->getEnabled();

        return $isCompanyTaxFree && $isEu;
    }

    // If shopware PR (NEXT-36528) has applied, and we raise our min compatibility to that version accordingly.
    // The country then has a Property isEu which we can use to determine if the country is in the EU.
    // To reduce redundancy with the EU membership the `$deliveryCountries` setting is then no longer necessary.
    // https://github.com/shopware/shopware/pull/3752
    private function isEu(CountryEntity $country, array $deliveryCountries): bool
    {
        if ($country->has('isEu')) {
            return $country->getIsEu();
        }

        return in_array($country->getId(), $deliveryCountries, true);
    }
}
