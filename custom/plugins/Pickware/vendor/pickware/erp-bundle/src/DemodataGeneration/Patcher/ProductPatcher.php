<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\DemodataGeneration\Patcher;

use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Json\Json;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderDefinition;
use Shopware\Core\Content\Media\Event\MediaUploadedEvent;
use Shopware\Core\Content\Media\File\FileFetcher;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\Message\UpdateThumbnailsMessage;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ProductPatcher
{
    // The maximum number of results that can be fetched from the unsplash API in one request.
    // For more details see: https://unsplash.com/documentation#get-a-random-photo
    private const UNSPLASH_API_MAX_COUNT = 30;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        private readonly FileFetcher $fileFetcher,
        private readonly FileSaver $fileSaver,
        private readonly EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: 'messenger.default_bus')]
        private readonly MessageBusInterface $messageBus,
    ) {}

    public function patch(Context $context): void
    {
        $this->patchProductNumbers($context);
    }

    /**
     * Updates existing products by adding and assigning random cover images fetched from the unsplash API.
     * @Note The unsplash API is rate limited. For a free unreviewed access key, the limit is 50 requests per hour.
     * To simplify this method, it ignores the rate limit since it is sufficient to patch all products of a development
     * shop. If the rate limit is exceeded however the method eventually will throw an undefined array key exception.
     */
    public function patchProductImages(Context $context): void
    {
        $accessKey = $_ENV['UNSPLASH_ACCESS_KEY'];
        if (empty($accessKey)) {
            throw new InvalidArgumentException(
                'Environment variable "UNSPLASH_ACCESS_KEY" is missing. To patch all product images you need an ' .
                'unsplash account to receive an access key.',
            );
        }

        $productIds = $this->entityManager->findIdsBy(ProductDefinition::class, [], $context);
        $mediaFolderId = $this->entityManager
            ->getOneBy(
                MediaDefaultFolderDefinition::class,
                ['entity' => ProductDefinition::ENTITY_NAME],
                $context,
                ['folder'],
            )
            ->getFolder()
            ->getId();

        $numberOfPages = ceil(count($productIds) / self::UNSPLASH_API_MAX_COUNT);
        for ($pageNumber = 0; $pageNumber < $numberOfPages; ++$pageNumber) {
            $imageResponse = file_get_contents(
                'https://api.unsplash.com/photos/random?orientation=squarish&count=' . self::UNSPLASH_API_MAX_COUNT . '&query=product&page=' . $pageNumber . '&client_id=' . $_ENV['UNSPLASH_ACCESS_KEY'],
            );
            $imageURLs = array_map(
                fn($imagePayload) => $imagePayload['urls']['small'],
                Json::decodeToArray($imageResponse),
            );

            $productsUpdatePayload = [];
            $mediaIds = [];
            // In some cases the unsplash api returns fewer images than requested.
            $batchSize = min(count($imageURLs), count($productIds));
            for ($i = 0; $i < $batchSize; ++$i) {
                $productId = array_shift($productIds);
                if ($productId === null) {
                    break;
                }

                $mediaId = Uuid::randomHex();
                $mediaIds[] = $mediaId;
                $productsUpdatePayload[] = [
                    'id' => $productId,
                    'cover' => [
                        'media' => [
                            'id' => $mediaId,
                            'mediaFolder' => [
                                'id' => $mediaFolderId,
                            ],
                        ],
                    ],
                ];
            }

            $this->entityManager->update(ProductDefinition::class, $productsUpdatePayload, $context);

            foreach ($productsUpdatePayload as $productUpdatePayload) {
                $this->saveMedia($productUpdatePayload['cover']['media']['id'], array_shift($imageURLs), $context);
            }

            $message = new UpdateThumbnailsMessage();
            $message->setMediaIds($mediaIds);
            $message->setContext($context);
            $this->messageBus->dispatch($message);
        }
    }

    /**
     * Updates existing products by changing the product numbers to actual numbers from the 'product' number range.
     */
    private function patchProductNumbers(Context $context): void
    {
        $productIds = $this->entityManager->findIdsBy(ProductDefinition::class, [], $context);

        $payloads = [];
        foreach ($productIds as $productId) {
            $payload = ['id' => $productId];
            $payloads[] = $this->getProductPayload($context, $payload);

            if (count($payloads) >= 50) {
                $this->entityManager->update(ProductDefinition::class, $payloads, $context);
                $payloads = [];
            }
        }
        $this->entityManager->update(ProductDefinition::class, $payloads, $context);

        $this->updateOrderLineItemProductNumbers($context);
    }

    /**
     * Updates existing order line items by syncing the product order number from the payload to the actual product
     * number of the respective product entity.
     */
    private function updateOrderLineItemProductNumbers(Context $context): void
    {
        $orderLineItems = $this->entityManager->findAll(OrderLineItemDefinition::class, $context, ['product']);

        $payloads = [];
        /** @var OrderLineItemEntity $orderLineItem */
        foreach ($orderLineItems as $orderLineItem) {
            if ($orderLineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                // Only update product numbers of line items of type "product"
                continue;
            }

            // When changing the "product" (in this case: the product number), reference ids must be part of the payload
            $payloads[] = [
                'id' => $orderLineItem->getId(),
                'productId' => $orderLineItem->getProductId(),
                'referencedId' => $orderLineItem->getProductId(),
                'payload' => array_merge(
                    $orderLineItem->getPayload(),
                    ['productNumber' => $orderLineItem->getProduct()->getProductNumber()],
                ),
            ];

            if (count($payloads) >= 50) {
                $this->entityManager->update(OrderLineItemDefinition::class, $payloads, $context);
                $payloads = [];
            }
        }

        if (count($payloads) > 0) {
            $this->entityManager->update(OrderLineItemDefinition::class, $payloads, $context);
        }
    }

    private function getProductPayload(Context $context, array $payload = []): array
    {
        $productNumber = $this->numberRangeValueGenerator->getValue('product', $context, null);

        return array_merge(
            [
                'productNumber' => $productNumber,
            ],
            $payload,
        );
    }

    /**
     * This method is derived from the MediaUploadController::upload method of shopware.
     */
    private function saveMedia(string $mediaId, string $url, Context $context): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), '') . '.jpg';
        $destination = $mediaId;

        $request = new Request([], ['url' => $url]);
        $imageFile = $this->fileFetcher->fetchFileFromURL($request, $tempFile);
        $this->fileSaver->persistFileToMedia(
            $imageFile,
            $destination,
            $mediaId,
            $context,
        );

        $this->eventDispatcher->dispatch(new MediaUploadedEvent($mediaId, $context));

        unlink($tempFile);
    }
}
