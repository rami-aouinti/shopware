<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockApi;

use InvalidArgumentException;
use JsonSerializable;
use Pickware\PhpStandardLibrary\Collection\CountingMap;
use Pickware\PickwareErpStarter\Batch\Model\BatchStockMovementMappingOrigin;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class StockMovement implements JsonSerializable
{
    /**
     * @param int<1,max> $quantity
     * @param CountingMap<string>|null $batches
     */
    private function __construct(
        private string $id,
        private readonly string $productId,
        private readonly int $quantity,
        private readonly StockLocationReference $source,
        private readonly StockLocationReference $destination,
        private readonly ?string $comment = null,
        private ?string $userId = null,
        private readonly ?string $stockMovementProcessId = null,
        private readonly ?CountingMap $batches = null,
    ) {
        if ($quantity < 1) {
            throw new InvalidArgumentException(sprintf(
                'Property quantity of class %s has to be greater than 0.',
                self::class,
            ));
        }

        if ($batches && $batches->getTotalCount() !== $quantity) {
            throw new InvalidArgumentException(sprintf(
                'The total count of batches (%d) must match the quantity (%d) of the stock movement.',
                $batches->getTotalCount(),
                $quantity,
            ));
        }
    }

    /**
     * @param array{
     *     source: StockLocationReference,
     *     destination: StockLocationReference,
     *     productId: string,
     *     quantity: int,
     *     id?: string|null,
     *     comment?: string|null,
     *     userId?: string|null,
     *     stockMovementProcessId?: string|null,
     *     batches?: CountingMap<string>|null,
     * } $array
     */
    public static function create(array $array): self
    {
        $source = $array['source'];
        $destination = $array['destination'];
        $quantity = $array['quantity'];
        if ($quantity < 0) {
            $quantity = -1 * $quantity;
            // Swap source and destination
            $temp = $source;
            $source = $destination;
            $destination = $temp;
        }

        return new self(
            $array['id'] ?? Uuid::randomHex(),
            $array['productId'],
            $quantity,
            $source,
            $destination,
            $array['comment'] ?? null,
            $array['userId'] ?? null,
            $array['stockMovementProcessId'] ?? null,
            $array['batches'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return array_merge(
            [
                'id' => $this->id,
                'quantity' => $this->quantity,
                'productId' => $this->productId,
                'comment' => $this->comment,
                'userId' => $this->userId,
                'stockMovementProcessId' => $this->stockMovementProcessId,
                'batchMappings' => $this->batches ? $this->batches->mapToList(fn(string $batchId, int $quantity) => [
                    'batchId' => $batchId,
                    'productId' => $this->productId,
                    'quantity' => $quantity,
                    'origin' => BatchStockMovementMappingOrigin::UserCreated,
                ]) : [],
            ],
            $this->getSource()->toSourcePayload(),
            $this->getDestination()->toDestinationPayload(),
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getSource(): StockLocationReference
    {
        return $this->source;
    }

    public function getDestination(): StockLocationReference
    {
        return $this->destination;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function getStockMovementProcessId(): ?string
    {
        return $this->stockMovementProcessId;
    }

    /**
     * @return CountingMap<string>|null
     */
    public function getBatches(): ?CountingMap
    {
        return $this->batches;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'productId' => $this->productId,
            'quantity' => $this->quantity,
            'source' => $this->source,
            'destination' => $this->destination,
            'comment' => $this->comment,
            'userId' => $this->userId,
            'stockMovementProcessId' => $this->stockMovementProcessId,
            'batches' => $this->batches?->jsonSerialize(),
        ];
    }
}
