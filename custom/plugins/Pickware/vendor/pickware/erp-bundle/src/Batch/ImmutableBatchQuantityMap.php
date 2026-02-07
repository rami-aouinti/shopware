<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Batch;

use Countable;
use IteratorAggregate;
use JsonSerializable;
use Pickware\PhpStandardLibrary\Collection\CountingMap;
use Traversable;

/**
 * @implements IteratorAggregate<string, int>
 */
readonly class ImmutableBatchQuantityMap implements Countable, IteratorAggregate, JsonSerializable
{
    /**
     * @var array<string, int>
     */
    private array $batchQuantities;

    /**
     * @param array<string, int> $batchQuantities
     */
    public function __construct(array $batchQuantities = [])
    {
        $this->batchQuantities = array_filter($batchQuantities, fn(int $quantity) => $quantity !== 0);
    }

    public function add(self $other): self
    {
        $newQuantities = $this->batchQuantities;
        foreach ($other->batchQuantities as $batchId => $quantity) {
            $newQuantities[$batchId] = ($newQuantities[$batchId] ?? 0) + $quantity;
        }

        return new self($newQuantities);
    }

    public function has(string $batchId): bool
    {
        return array_key_exists($batchId, $this->batchQuantities);
    }

    public function negate(): self
    {
        return new self(array_map(fn(int $quantity) => -1 * $quantity, $this->batchQuantities));
    }

    public function getTotal(): int
    {
        return array_sum($this->batchQuantities);
    }

    /**
     * @return CountingMap<string>
     */
    public function asCountingMap(): CountingMap
    {
        return new CountingMap($this->batchQuantities);
    }

    public function count(): int
    {
        return count($this->batchQuantities);
    }

    public function getIterator(): Traversable
    {
        yield from $this->batchQuantities;
    }

    /**
     * @return array<string, int>
     */
    public function jsonSerialize(): array
    {
        return $this->batchQuantities;
    }
}
