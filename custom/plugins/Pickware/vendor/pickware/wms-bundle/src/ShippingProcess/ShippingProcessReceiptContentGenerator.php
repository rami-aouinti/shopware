<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\ShippingProcess;

use Pickware\DalBundle\ContextFactory;
use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareErpStarter\Translation\Translator;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Pickware\PickwareWms\Delivery\Model\DeliveryEntity;
use Pickware\PickwareWms\Delivery\Model\DeliveryLineItemEntity;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessEntity;
use Pickware\PickwareWms\ShippingProcess\Model\ShippingProcessDefinition;
use Pickware\PickwareWms\ShippingProcess\Model\ShippingProcessEntity;
use Pickware\PickwareWms\ShippingProcess\Model\ShippingProcessStateMachine;
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

class ShippingProcessReceiptContentGenerator
{
    private const BARCODE_ACTION_PREFIX_DEFERRED = '^F';

    public function __construct(
        private readonly ContextFactory $contextFactory,
        private readonly EntityManager $entityManager,
        private readonly ProductNameFormatterService $productNameFormatterService,
        private readonly Translator $translator,
        private readonly BarcodeGeneratorSVG $barcodeGenerator = new BarcodeGeneratorSVG(),
    ) {}

    /**
     * @return array{
     *  shippingProcessNumber: string,
     *  shippingProcessState: string,
     *  warehouse: WarehouseEntity,
     *  title: string,
     *  productListTitle: string,
     *  productNameQuantities: array<array{
     *      productNumber: string,
     *      name: string,
     *      quantity: int
     *  }>,
     *  barcode: string,
     *  localeCode: string,
     *  userName: ?string,
     *  orders: array<\Shopware\Core\Checkout\Order\OrderEntity>
     * }
     */
    public function generateForShippingProcess(
        string $shippingProcessId,
        string $languageId,
        Context $context,
    ): array {
        $contextWithLanguage = $this->contextFactory->createLocalizedContext($languageId, $context);

        /** @var ShippingProcessEntity $shippingProcess */
        $shippingProcess = $this->entityManager->getByPrimaryKey(
            ShippingProcessDefinition::class,
            $shippingProcessId,
            $contextWithLanguage,
            [
                'pickingProcesses.deliveries.order.lineItems.product',
                'pickingProcesses.deliveries.order.billingAddress.country',
                'pickingProcesses.deliveries.order.billingAddress.countryState',
                'pickingProcesses.deliveries.order.deliveries.shippingMethod.translated',
                'pickingProcesses.deliveries.order.deliveries.shippingOrderAddress.country',
                'pickingProcesses.deliveries.order.deliveries.shippingOrderAddress.countryState',
                'pickingProcesses.deliveries.order.transactions.paymentMethod.translated',
                'pickingProcesses.preCollectingStockContainer.stocks',
                'pickingProcesses.deliveries.lineItems.product',
                'pickingProcesses.deliveries.state',
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

        $shippingProcessState = $shippingProcess->getState()->getTechnicalName();
        $localeCode = $language->getLocale()->getCode();

        $this->translator->setTranslationLocale($localeCode, $context);

        $stateSpecificContent = match ($shippingProcessState) {
            ShippingProcessStateMachine::STATE_DEFERRED => [
                'title' => $this->translator->translate('pickware-wms.shipping-process-receipt.title.deferred'),
                'productListTitle' => $this->translator->translate(
                    'pickware-wms.shipping-process-receipt.product-list-title.deferred',
                ),
                'barcode' => $this->generateBarcodeDataUrl(
                    self::BARCODE_ACTION_PREFIX_DEFERRED,
                    $shippingProcess->getNumber(),
                ),
            ],
            default => throw ShippingProcessException::invalidShippingProcessStateForAction( // phpcs:ignore VIISON.Arrays.ArrayDeclaration.IndexNoNewline -- false positive
                $shippingProcessId,
                $shippingProcessState,
                [ShippingProcessStateMachine::STATE_DEFERRED],
            ),
        };

        $displayQuantitiesByProduct = new ProductQuantityImmutableCollection();
        if ($shippingProcessState === ShippingProcessStateMachine::STATE_DEFERRED) {
            $displayQuantitiesByProduct = $this->calculateRemainingQuantities($shippingProcess);
        }

        $productsById = ImmutableCollection::create($shippingProcess->getPickingProcesses())
            ->flatMap(fn(PickingProcessEntity $pickingProcess) => $pickingProcess->getDeliveries()->getElements())
            ->flatMap(fn(DeliveryEntity $delivery) => $delivery->getLineItems()->getElements())
            ->map(fn(DeliveryLineItemEntity $lineItem) => $lineItem->getProduct())
            ->reduce([], fn(array $productsById, ProductEntity $product) => [
                ...$productsById,
                $product->getId() => $product,
            ]);
        $productNamesById = $this->productNameFormatterService->getFormattedProductNames(
            $displayQuantitiesByProduct->getProductIds()->asArray(),
            [],
            $contextWithLanguage,
        );

        $productNameQuantities = $displayQuantitiesByProduct
            ->map(fn(ProductQuantity $productQuantity) => [
                'productNumber' => $productsById[$productQuantity->getProductId()]->getProductNumber(),
                'name' => $productNamesById[$productQuantity->getProductId()],
                'quantity' => $productQuantity->getQuantity(),
            ])
            ->sorted(fn(array $lhs, array $rhs) => $lhs['productNumber'] <=> $rhs['productNumber'])
            ->asArray();

        $orders = ImmutableCollection::create($shippingProcess->getPickingProcesses())
            ->flatMap(fn(PickingProcessEntity $pickingProcess) => $pickingProcess->getDeliveries()->getElements())
            ->compactMap(fn(DeliveryEntity $delivery) => $delivery->getOrder())
            ->asArray();

        return [
            'shippingProcessNumber' => $shippingProcess->getNumber(),
            'shippingProcessState' => $shippingProcessState,
            'warehouse' => $shippingProcess->getWarehouse(),
            'title' => $stateSpecificContent['title'],
            'productListTitle' => $stateSpecificContent['productListTitle'],
            'productNameQuantities' => $productNameQuantities,
            'barcode' => $stateSpecificContent['barcode'],
            'localeCode' => $localeCode,
            'userName' => $this->getUserFullName($context),
            'orders' => $orders,
        ];
    }

    private function calculateRemainingQuantities(ShippingProcessEntity $shippingProcess): ProductQuantityImmutableCollection
    {
        return ImmutableCollection::create($shippingProcess->getPickingProcesses())
            ->filter(fn(PickingProcessEntity $pickingProcess) => $pickingProcess->getPreCollectingStockContainerId() !== null)
            ->flatMap(
                fn(PickingProcessEntity $pickingProcess) => $pickingProcess->getPreCollectingStockContainer()->getStocks()->getProductQuantities(),
                ProductQuantityImmutableCollection::class,
            )
            ->groupByProductId();
    }

    private function generateBarcodeDataUrl(string $prefix, string $shippingProcessNumber): string
    {
        $code = $prefix . $shippingProcessNumber;
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
