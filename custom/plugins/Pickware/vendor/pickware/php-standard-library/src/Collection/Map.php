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
use Iterator;
use JsonSerializable;
use stdClass;

/**
 * @template Key of array-key
 * @template Value
 * @implements ArrayAccess<Key, Value>
 * @implements Iterator<array{Key, Value}>
 */
class Map implements Countable, ArrayAccess, Iterator, JsonSerializable
{
    /**
     * @param array<Key, Value> $data
     */
    final public function __construct(private array $data = []) {}

    /**
     * @param array<Key, Value> $data
     * @return static<Key, Value>
     */
    final public static function create(array $data = []): static
    {
        return new static($data);
    }

    /**
     * @param list<Key> $data
     * @param Value $defaultValue
     * @return static<Key, Value>
     */
    public static function createWithDefault(array $data = [], mixed $defaultValue = null): static
    {
        return self::create(array_combine(
            $data,
            array_fill(0, count($data), $defaultValue),
        ));
    }

    /**
     * @param Key $key
     * @param Value $value
     */
    public function set(int|string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * @param Key $key
     * @return Value|null
     */
    public function get(int|string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * @param Key $key
     */
    public function has(int|string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Merges the value of the given key with the given value using the given merge function.
     * If the key does not exist, the value is set to the given value. Otherwise, the value is
     * merged using the given merge function. Returns the merged value.
     *
     * @param Key $key
     * @param Value $value
     * @param pure-callable(Value, Value): Value $mergeFunction
     * @return Value
     */
    public function mergeEntry(int|string $key, mixed $value, callable $mergeFunction): mixed
    {
        if (!$this->has($key)) {
            $this->set($key, $value);

            return $value;
        }

        $oldValue = $this->get($key);
        $newValue = $mergeFunction($oldValue, $value);
        $this->set($key, $newValue);

        return $newValue;
    }

    /**
     * Merges the given map into this map using the given merge function.
     *
     * @param Map<Key, Value> $other
     * @param pure-callable(Value, Value): Value $mergeFunction
     */
    public function merge(Map $other, callable $mergeFunction): void
    {
        foreach ($other->data as $key => $value) {
            $this->mergeEntry($key, $value, $mergeFunction);
        }
    }

    public function count(): int
    {
        return count($this->data);
    }

    /**
     * @return list<Key>
     */
    public function getKeys(): array
    {
        return array_keys($this->data);
    }

    /**
     * @return list<Value>
     */
    public function getValues(): array
    {
        return array_values($this->data);
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * @return false|Value
     */
    public function current(): mixed
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
     * @return stdClass|array<Key, Value>
     */
    public function jsonSerialize(): stdClass|array
    {
        return empty($this->data) ? new stdClass() : $this->data;
    }
}
