<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProcess;

use Pickware\DalBundle\ContextFactory;
use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareErpStarter\Translation\Translator;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Pickware\PickwareWms\Delivery\Model\DeliveryEntity;
use Pickware\PickwareWms\Delivery\Model\DeliveryLineItemEntity;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessDefinition;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessEntity;
use Pickware\ShopwareExtensionsBundle\Context\ContextExtension;
use Pickware\ShopwareExtensionsBundle\Product\ProductNameFormatterService;
use Picqer\Barcode\BarcodeGenerator;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\System\User\UserEntity;

class PickingProcessReceiptContentGenerator
{
    private const BARCODE_ACTION_PREFIX_DEFERRED_SINGLE = '^0';
    private const BARCODE_ACTION_PREFIX_DEFERRED_OTHER = '^7';
    private const BARCODE_ACTION_PREFIX_PICKED = '^E';

    public function __construct(
        private readonly ContextFactory $contextFactory,
        private readonly EntityManager $entityManager,
        private readonly ProductNameFormatterService $productNameFormatterService,
        private readonly Translator $translator,
        private readonly BarcodeGeneratorSVG $barcodeGenerator = new BarcodeGeneratorSVG(),
    ) {}

    /**
     * @return array{
     *  pickingProcessNumber: string,
     *  pickingProcessState: string,
     *  warehouse: WarehouseEntity,
     *  title: string,
     *  productListTitle?: string,
     *  productNameQuantities?: array<array{
     *      productNumber: string,
     *      name: string,
     *      quantity: int
     *  }>,
     *  orderList?: array<array{
     *      orderNumber: string,
     *      shippingAddressName: string,
     *      shippingMethodName: string,
     *      order: ?\Shopware\Core\Checkout\Order\OrderEntity
     *  }>,
     *  barcode: string,
     *  localeCode: string,
     *  userName: ?string,
     *  orderNumber: ?string,
     *  orderDate: ?\DateTimeInterface,
     *  order: ?\Shopware\Core\Checkout\Order\OrderEntity,
     *  shippingAddress: ?\Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity
     * }
     */
    public function generateForPickingProcess(
        string $pickingProcessId,
        string $languageId,
        Context $context,
    ): array {
        $contextWithLanguage = $this->contextFactory->createLocalizedContext($languageId, $context);

        /** @var PickingProcessEntity $pickingProcess */
        $pickingProcess = $this->entityManager->getByPrimaryKey(
            PickingProcessDefinition::class,
            $pickingProcessId,
            $contextWithLanguage,
            [
                'deliveries.lineItems.product',
                'deliveries.stockContainer.stocks',
                'deliveries.order.lineItems.product',
                'deliveries.order.billingAddress.country',
                'deliveries.order.billingAddress.countryState',
                'deliveries.order.deliveries.shippingMethod.translated',
                'deliveries.order.deliveries.shippingOrderAddress.country',
                'deliveries.order.deliveries.shippingOrderAddress.countryState',
                'deliveries.order.transactions.paymentMethod.translated',
                'deliveries.state',
                'preCollectingStockContainer.stocks',
                'warehouse',
                'state',
            ],
        );

        /** @var LanguageEntity $language */
        $language = $this->entityManager->getByPrimaryKey(
            LanguageDefinition::class,
            $languageId,
            $context,
            ['locale'],
        );

        $pickingProcessState = $pickingProcess->getState()->getTechnicalName();
        $pickingMode = $pickingProcess->getPickingMode();
        $localeCode = $language->getLocale()->getCode();

        $this->translator->setTranslationLocale($localeCode, $context);

        $stateSpecificContent = match ($pickingProcessState) {
            PickingProcessStateMachine::STATE_DEFERRED => [
                'title' => $this->translator->translate('pickware-wms.picking-process-receipt.title.deferred'),
                'productListTitle' => $this->translator->translate(
                    'pickware-wms.picking-process-receipt.product-list-title.deferred',
                ),
                'barcode' => $this->generateBarcodeDataUrl(
                    ($pickingMode === PickingProcessDefinition::PICKING_MODE_SINGLE) ? self::BARCODE_ACTION_PREFIX_DEFERRED_SINGLE : self::BARCODE_ACTION_PREFIX_DEFERRED_OTHER,
                    $pickingProcess->getNumber(),
                ),
            ],
            PickingProcessStateMachine::STATE_PICKED => [ // phpcs:ignore VIISON.Arrays.ArrayDeclaration.IndexNoNewline -- false positive
                'title' => $this->translator->translate('pickware-wms.picking-process-receipt.title.picked'),
                'productListTitle' => $this->translator->translate(
                    'pickware-wms.picking-process-receipt.product-list-title.picked',
                ),
                'barcode' => $this->generateBarcodeDataUrl(
                    self::BARCODE_ACTION_PREFIX_PICKED,
                    $pickingProcess->getNumber(),
                ),
            ],
            default => throw PickingProcessException::invalidPickingProcessStateForAction( // phpcs:ignore VIISON.Arrays.ArrayDeclaration.IndexNoNewline -- false positive
                $pickingProcessId,
                $pickingProcessState,
                [
                    PickingProcessStateMachine::STATE_DEFERRED,
                    PickingProcessStateMachine::STATE_PICKED,
                ],
            ),
        };

        $content = [
            'pickingProcessNumber' => $pickingProcess->getNumber(),
            'pickingProcessState' => $pickingProcessState,
            'warehouse' => $pickingProcess->getWarehouse(),
            'title' => $stateSpecificContent['title'],
            'barcode' => $stateSpecificContent['barcode'],
            'localeCode' => $localeCode,
            'userName' => $this->getUserFullName($context),
            'orderNumber' => null,
            'orderDate' => null,
            'order' => null,
            'shippingAddress' => null,
        ];

        if ($pickingProcess->getPickingMode() === PickingProcessDefinition::PICKING_MODE_SINGLE) {
            $content['productListTitle'] = $stateSpecificContent['productListTitle'];
            $content['productNameQuantities'] = $this->generateProductListData(
                $pickingProcess,
                $pickingProcessState,
                $contextWithLanguage,
            );

            $order = $pickingProcess->getDeliveries()->first()?->getOrder();
            if ($order) {
                $content['orderNumber'] = $order->getOrderNumber();
                $content['orderDate'] = $order->getOrderDateTime();

                // Get the shipping address from the first delivery
                $firstDelivery = $order->getDeliveries()?->first();
                if ($firstDelivery) {
                    $content['shippingAddress'] = $firstDelivery->getShippingOrderAddress();
                }

                $content['order'] = $order;
            }
        } else {
            $content['orderList'] = $this->generateOrderListData($pickingProcess);
        }

        return $content;
    }

    /**
     * @return array<array{productNumber: string, name: string, quantity: int}>
     */
    private function generateProductListData(
        PickingProcessEntity $pickingProcess,
        string $pickingProcessState,
        Context $context,
    ): array {
        $deliveryLineItemQuantitiesByProduct = ImmutableCollection::create($pickingProcess->getDeliveries())
            ->filter(fn(DeliveryEntity $delivery) => $delivery->getState()->getTechnicalName() !== DeliveryStateMachine::STATE_CANCELLED)
            ->flatMap(fn(DeliveryEntity $delivery) => $delivery->getLineItems()->getElements())
            ->map(
                fn(DeliveryLineItemEntity $lineItem) => new ProductQuantity($lineItem->getProductId(), $lineItem->getQuantity()),
                ProductQuantityImmutableCollection::class,
            )
            ->groupByProductId();
        $pickedQuantitiesByProduct = $this->calculatePickedQuantities($pickingProcess);

        if ($pickingProcessState === PickingProcessStateMachine::STATE_DEFERRED) {
            $displayQuantitiesByProduct = $deliveryLineItemQuantitiesByProduct
                ->subtract($pickedQuantitiesByProduct)
                ->filter(fn(ProductQuantity $productQuantity) => $productQuantity->getQuantity() > 0);
        } elseif ($pickingProcessState === PickingProcessStateMachine::STATE_PICKED) {
            $displayQuantitiesByProduct = $pickedQuantitiesByProduct;
        } else {
            $displayQuantitiesByProduct = new ProductQuantityImmutableCollection();
        }

        $productsById = ImmutableCollection::create($pickingProcess->getDeliveries())
            ->flatMap(fn(DeliveryEntity $delivery) => $delivery->getLineItems()->getElements())
            ->map(fn(DeliveryLineItemEntity $lineItem) => $lineItem->getProduct())
            ->reduce([], fn(array $productsById, ProductEntity $product) => [
                ...$productsById,
                $product->getId() => $product,
            ]);
        $productNamesById = $this->productNameFormatterService->getFormattedProductNames(
            $displayQuantitiesByProduct->getProductIds()->asArray(),
            [],
            $context,
        );

        return $displayQuantitiesByProduct
            ->map(fn(ProductQuantity $productQuantity) => [
                'productNumber' => $productsById[$productQuantity->getProductId()]->getProductNumber(),
                'name' => $productNamesById[$productQuantity->getProductId()],
                'quantity' => $productQuantity->getQuantity(),
            ])
            ->sorted(fn(array $lhs, array $rhs) => $lhs['productNumber'] <=> $rhs['productNumber'])
            ->asArray();
    }

    /**
     * @return array<array{orderNumber: string, shippingMethodName: string}>
     */
    private function generateOrderListData(PickingProcessEntity $pickingProcess): array
    {
        $orderList = [];

        foreach ($pickingProcess->getDeliveries() as $delivery) {
            if (!$delivery->getOrderId() || $delivery->getState()->getTechnicalName() === DeliveryStateMachine::STATE_CANCELLED) {
                continue;
            }

            $order = $delivery->getOrder();
            $shippingMethodName = '';
            $shippingAddressName = '';
            if ($order->getDeliveries()->count() > 0) {
                $firstOrderDelivery = $order->getDeliveries()->first();
                $shippingMethod = $firstOrderDelivery->getShippingMethod();
                $shippingAddress = $firstOrderDelivery->getShippingOrderAddress();
                if ($shippingAddress) {
                    $nameParts = array_filter(array_map('trim', [
                        $shippingAddress->getCompany(),
                        trim($shippingAddress->getFirstName()) . ' ' . trim($shippingAddress->getLastName()),
                    ]));
                    $shippingAddressName = implode(', ', $nameParts);
                }
                $shippingMethodName = $shippingMethod?->getTranslated()['name'] ?? $shippingMethod?->getName() ?? '';
            }

            $orderList[] = [
                'orderNumber' => $order->getOrderNumber(),
                'shippingAddressName' => $shippingAddressName,
                'shippingMethodName' => $shippingMethodName,
                'order' => $order,
            ];
        }

        return $orderList;
    }

    private function calculatePickedQuantities(PickingProcessEntity $pickingProcess): ProductQuantityImmutableCollection
    {
        $pickedQuantities = ImmutableCollection::create($pickingProcess->getDeliveries())
            ->filter(fn(DeliveryEntity $delivery) => $delivery->getStockContainerId() !== null && $delivery->getState()->getTechnicalName() !== DeliveryStateMachine::STATE_CANCELLED)
            ->flatMap(
                fn(DeliveryEntity $delivery) => $delivery->getStockContainer()->getStocks()->getProductQuantities(),
                ProductQuantityImmutableCollection::class,
            );
        if ($pickingProcess->getPreCollectingStockContainerId()) {
            $pickedQuantities = $pickedQuantities->merge(
                $pickingProcess
                    ->getPreCollectingStockContainer()
                    ->getStocks()
                    ->getProductQuantities(),
            );
        }

        return $pickedQuantities->groupByProductId();
    }

    private function generateBarcodeDataUrl(string $prefix, string $pickingProcessNumber): string
    {
        $code = $prefix . $pickingProcessNumber;
        $barcode = $this->barcodeGenerator->getBarcode(
            barcode: $code,
            type: BarcodeGenerator::TYPE_CODE_128,
            widthFactor: 1,
        );

        return 'data:image/svg+xml;base64,' . base64_encode($barcode);
    }

    private function getUserFullName(Context $context): ?string
    {
        $userId = ContextExtension::findUserId($context);
        if (!$userId) {
            return null;
        }

        /** @var UserEntity|null $user */
        $user = $this->entityManager->findByPrimaryKey(
            UserDefinition::class,
            $userId,
            $context,
        );
        if (!$user) {
            return null;
        }

        return trim($user->getFirstName() . ' ' . $user->getLastName());
    }
}
