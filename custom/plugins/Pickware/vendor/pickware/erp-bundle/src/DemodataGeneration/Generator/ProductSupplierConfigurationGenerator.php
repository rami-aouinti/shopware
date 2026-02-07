<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\DemodataGeneration\Generator;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationDefinition;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierDefinition;
use Pickware\PickwareErpStarter\Supplier\PurchasePriceSynchronizer\PurchasePriceSynchronizer;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * This generator generates product-supplier-configurations.
 */
#[AutoconfigureTag('shopware.demodata_generator')]
class ProductSupplierConfigurationGenerator implements DemodataGeneratorInterface
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getDefinition(): string
    {
        return ProductSupplierConfigurationDefinition::class;
    }

    public function generate(int $number, DemodataContext $demoDataContext, array $options = []): void
    {
        /** @var ProductCollection $products */
        $products = $this->entityManager->findAll(
            ProductDefinition::class,
            $demoDataContext->getContext(),
            ['tax'],
        );
        if (count($products) === 0) {
            $demoDataContext->getConsole()->text('No products found. Skipping product supplier configuration generation.');

            return;
        }

        /** @var string[] $supplierIds */
        $supplierIds = $this->entityManager->findAllIds(SupplierDefinition::class, $demoDataContext->getContext());
        if (count($supplierIds) === 0) {
            $demoDataContext->getConsole()->text('No suppliers found. Skipping product supplier configuration generation.');

            return;
        }

        $payloads = [];
        foreach ($products as $product) {
            foreach ($supplierIds as $supplierId) {
                $payloads[] = $this->getProductSupplierConfigurationPayload($product, $supplierId, $demoDataContext);
            }
        }
        $demoDataContext->getConsole()->progressStart(count($payloads));

        $numberOfWrittenItems = 0;
        $payloadChunks = array_chunk($payloads, length: 50);
        foreach ($payloadChunks as $payloadChunk) {
            $this->entityManager->create(
                ProductSupplierConfigurationDefinition::class,
                $payloadChunk,
                $demoDataContext->getContext(),
            );
            $numberOfWrittenItems += count($payloadChunk);
            $demoDataContext->getConsole()->progressAdvance($numberOfWrittenItems);
        }

        $demoDataContext->getConsole()->progressFinish();
        $demoDataContext->getConsole()->text(sprintf(
            '%s product supplier configurations have been updated and assigned to suppliers.',
            count($payloads),
        ));
    }

    private function getProductSupplierConfigurationPayload(
        ProductEntity $product,
        string $supplierId,
        DemodataContext $demoDataContext,
    ): array {
        $faker = $demoDataContext->getFaker();

        $purchaseStepOptions = [
            1,
            5,
            10,
            25,
            50,
        ];
        $purchaseSteps = $purchaseStepOptions[array_rand($purchaseStepOptions)];
        // [5..50] in steps of 5
        $minPurchase = random_int(1, 10) * 5;
        $netPrice = random_int(0, 10000) / 100.0;
        $taxRate = $product->getTax()?->getTaxRate() ?? 0.0;
        $purchasePrices = [
            'c' . Defaults::CURRENCY => [
                'currencyId' => Defaults::CURRENCY,
                'net' => $netPrice,
                'gross' => $netPrice * (1 + $taxRate / 100),
                'linked' => true,
            ],
        ];

        /** We purposely don't set a purchase price since that should be taken care by the @see PurchasePriceSynchronizer */
        return [
            'id' => Uuid::randomHex(),
            'productId' => $product->getId(),
            'supplierId' => $supplierId,
            'minPurchase' => $minPurchase,
            'purchaseSteps' => $purchaseSteps,
            'purchasePrices' => $purchasePrices,
            'supplierProductNumber' => sprintf(
                '%s%s',
                mb_strtoupper($faker->randomLetter()),
                random_int(10000, 99999),
            ),
        ];
    }
}
