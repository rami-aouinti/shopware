<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PhpStandardLibrary\Collection;

use ArrayAccess;
use Countable;
use InvalidArgumentException;
use Iterator;
use JsonSerializable;
use stdClass;

/**
 * @template Key of array-key
 * @implements ArrayAccess<Key, non-negative-int>
 * @implements Iterator<non-negative-int>
 */
class CountingMap implements Countable, ArrayAccess, Iterator, JsonSerializable
{
    /** @var array<Key, non-negative-int> */
    private array $data = [];

    /**
     * @param array<Key, non-negative-int> $initial
     */
    public function __construct(array $initial = [])
    {
        foreach ($initial as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * @param Key $key
     * @param non-negative-int $amount
     */
    public function set(int|string $key, int $amount): void
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount must be non-negative');
        }
        if ($amount === 0) {
            unset($this->data[$key]);

            return;
        }
        $this->data[$key] = $amount;
    }

    /**
     * @template T of array-key
     * @param list<array{T, non-negative-int}> $tuples
     * @return self<T>
     */
    public static function fromTuples(array $tuples): self
    {
        $countingMap = new self();
        foreach ($tuples as [$key, $amount]) {
            $countingMap->add($key, $amount);
        }

        return $countingMap;
    }

    /**
     * @param Key $key
     * @param non-negative-int $amount
     */
    public function add(int|string $key, int $amount): void
    {
        $newAmount = ($this->data[$key] ?? 0) + $amount;
        $this->set($key, $newAmount);
    }

    /**
     * @param Key $key
     * @return non-negative-int
     */
    public function get(int|string $key): int
    {
        return $this->data[$key] ?? 0;
    }

    /**
     * @param Key $key
     */
    public function has(int|string $key): bool
    {
        return ($this->data[$key] ?? 0) > 0;
    }

    /**
     * @param CountingMap<Key> $other
     */
    public function isSubsetOf(self $other): bool
    {
        foreach ($this->data as $key => $amount) {
            if ($amount > $other->get($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return non-negative-int
     */
    public function count(): int
    {
        return count(array_filter($this->data, fn(int $quantity) => $quantity > 0));
    }

    /**
     * @param CountingMap<Key> $other
     */
    public function subtractMap(self $other): void
    {
        if (!$other->isSubsetOf($this)) {
            throw new InvalidArgumentException('Cannot subtract a map that is not a subset of the current map.');
        }

        foreach ($other->data as $key => $amount) {
            $newAmount = ($this->data[$key] ?? 0) - $amount;
            $this->set($key, $newAmount);
        }
    }

    /**
     * @return array<Key>
     */
    public function getKeys(): array
    {
        return array_keys($this->data);
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * @return false|non-negative-int
     */
    public function current(): false|int
    {
        return current($this->data);
    }

    public function next(): void
    {
        next($this->data);
    }

    public function key(): string|int|null
    {
        return key($this->data);
    }

    public function valid(): bool
    {
        return key($this->data) !== null;
    }

    public function rewind(): void
    {
        reset($this->data);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set($offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }

    /**
     * @return array<Key, non-negative-int>
     */
    public function asArray(): array
    {
        return $this->data;
    }

    /**
     * @return non-negative-int
     */
    public function getTotalCount(): int
    {
        return array_sum($this->data);
    }

    /**
     * @template ReturnType
     * @param callable(Key, non-negative-int):ReturnType $callback
     * @return list<ReturnType>
     */
    public function mapToList(callable $callback): array
    {
        return array_map(
            $callback,
            array_keys($this->data),
            $this->data,
        );
    }

    /**
     * @template ReturnType
     * @param callable(Key, non-negative-int):ReturnType $callback
     * @return ImmutableCollection<ReturnType>
     */
    public function mapToImmutableCollection(callable $callback): ImmutableCollection
    {
        return ImmutableCollection::fromArray($this->mapToList($callback));
    }

    /**
     * Returns how many times the given $other CountingMap is contained in this CountingMap.
     *
     * E.g. if this CountingMap contains ['A' => 4, 'B' => 2] and the given $other CountingMap
     * contains ['A' => 1, 'B' => 1], this method will return 2, because the $other CountingMap
     * is contained twice in this CountingMap.
     *
     * Note: An empty CountingMap is considered a subset of any CountingMap, therefore calling
     * this method with an empty CountingMap will throw an InvalidArgumentException.
     *
     * @param CountingMap<Key> $other
     * @return non-negative-int
     */
    public function countOccurrencesOf(CountingMap $other): int
    {
        if ($other->isEmpty()) {
            throw new InvalidArgumentException('Cannot count occurrences of an empty CountingMap.');
        }

        return $other
            ->mapToImmutableCollection(
                /** @return non-negative-int */
                function(int|string $key, int $amount): int {
                    /** @var non-negative-int $value */
                    $value = intdiv($this->get($key), $amount);

                    return $value;
                },
            )
            ->minimum();
    }

    public function jsonSerialize(): mixed
    {
        return empty($this->data) ? new stdClass() : $this->data;
    }
}
