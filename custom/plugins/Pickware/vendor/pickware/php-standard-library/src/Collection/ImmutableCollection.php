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
use IteratorAggregate;
use JsonSerializable;
use RuntimeException;
use Shopware\Core\Framework\Struct\Collection;
use Traversable;

/**
 * Keep this object immutable!
 *
 * @template Element
 * @implements ArrayAccess<int, Element>
 * @implements IteratorAggregate<int, Element>
 */
class ImmutableCollection implements ArrayAccess, IteratorAggregate, Countable, JsonSerializable
{
    /**
     * @var Element[]
     */
    private readonly array $elements;

    /**
     * @param Element[]|Collection<Element> $elements
     */
    final public function __construct(Collection|array $elements = [])
    {
        $this->elements = array_values(($elements instanceof Collection) ? $elements->getElements() : $elements);
    }

    /**
     * Shortcut method so you do not have to use the `new` keyword
     * @template PassedElement
     * @param Collection<PassedElement>|array<PassedElement> $elements
     * @return static<PassedElement>
     */
    final public static function create(Collection|array $elements = []): static
    {
        return new static($elements);
    }

    /**
     * @template PassedElement
     * @param ($class is null ? list<mixed> : list<array<string, mixed>>) $array
     * @param null|class-string<PassedElement> $class
     * @return ($class is null ? static : static<PassedElement>)
     */
    public static function fromArray(array $array, ?string $class = null): static
    {
        if ($class) {
            return self::create($array)->map([$class, 'fromArray'], static::class);
        }

        return static::create($array);
    }

    final public function offsetExists(mixed $offset): bool
    {
        return isset($this->elements[$offset]);
    }

    /**
     * @return Element
     */
    final public function offsetGet(mixed $offset): mixed
    {
        return $this->elements[$offset];
    }

    final public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new RuntimeException('Cannot set a value on an immutable collection.');
    }

    final public function offsetUnset(mixed $offset): void
    {
        throw new RuntimeException('Cannot unset a value on an immutable collection.');
    }

    /**
     * @return array<int, Element>
     */
    final public function jsonSerialize(): array
    {
        return $this->elements;
    }

    /**
     * @param ?callable(Element):bool $callback
     * @return static<Element>
     */
    final public function filter(?callable $callback = null): static
    {
        return new static(array_values(array_filter($this->elements, $callback)));
    }

    /**
     * Only applicable to collections of arrays (value tuples).
     *
     * @param callable(mixed, mixed):bool $callback callback with one or more arguments. The elements of
     *      this collection will be spread out as arguments to the callback.
     */
    final public function filterTuple(callable $callback): static
    {
        return $this->filter(fn($elements) => $callback(...$elements));
    }

    /**
     * @template MappedElement
     * @template Return of self
     * @param callable(Element):MappedElement $callback
     * @param ?class-string<Return> $returnType With this you can change the type of the returned collection
     *      if you have an explicit implementation for ImmutableCollection<MappedElement>
     * @return ($returnType is null ? self<MappedElement> : Return)
     */
    final public function map(callable $callback, ?string $returnType = null): self
    {
        $returnType ??= self::class;
        if (!is_a($returnType, self::class, allow_string: true)) {
            throw new InvalidArgumentException(
                sprintf('Passed return type %s does not inherit from %s.', $returnType, self::class),
            );
        }

        return new $returnType(array_map($callback, $this->elements));
    }

    /**
     * Only applicable to collections of arrays (value tuples).
     *
     * @template MappedElement
     * @template Return of self
     * @param callable(mixed, mixed):MappedElement $callback callback with two arguments. The elements of
     *     this collection will be spread out as arguments to the callback.
     * @param ?class-string<Return> $returnType With this you can change the type of the returned collection
     *      if you have an explicit implementation for ImmutableCollection<MappedElement>
     * @return ($returnType is null ? self<MappedElement> : Return)
     */
    final public function mapTuple(callable $callback, ?string $returnType = null): self
    {
        $returnType ??= self::class;

        return $this->map(fn($elements) => $callback(...$elements), $returnType);
    }

    /**
     * @template OtherElement
     * @param self<OtherElement> $other
     * @return self<array{Element, OtherElement}>
     */
    final public function zip(self $other): self
    {
        return new self(array_map(null, $this->elements, $other->elements));
    }

    /**
     * @template Carry
     * @param Carry $initialValue
     * @param callable(Carry, Element):Carry $callback
     * @return Carry
     */
    final public function reduce(mixed $initialValue, callable $callback): mixed
    {
        return array_reduce($this->elements, $callback, $initialValue);
    }

    final public function sum(): float|int
    {
        return array_sum($this->elements);
    }

    /**
     * @param ImmutableCollection<Element> $other
     * @return static<Element>
     */
    final public function merge(self $other): static
    {
        if ($other::class !== $this::class) {
            throw new InvalidArgumentException('Can only merge instances of the same type.');
        }

        return new static(array_merge($this->elements, $other->elements));
    }

    /**
     * @return Traversable<Element>
     */
    final public function getIterator(): Traversable
    {
        yield from $this->elements;
    }

    final public function count(): int
    {
        return count($this->elements);
    }

    final public function isEmpty(): bool
    {
        return empty($this->elements);
    }

    /**
     * @param self<Element> $other
     */
    final public function equals(self $other): bool
    {
        if ($other::class !== $this::class) {
            throw new InvalidArgumentException('Can only compare instances of the same type.');
        }

        return $this->elements === $other->elements;
    }

    /**
     * @return Element[]
     */
    final public function asArray(): array
    {
        return $this->elements;
    }

    /**
     * Splits this collection into chunks of the given size.
     *
     * @param positive-int $size length of each chunk
     * @return self<Element>[]
     */
    public function chunk(int $size): array
    {
        return array_map(fn(array $chunk) => new self($chunk), array_chunk($this->elements, $size));
    }

    /**
     * @param Element $search
     */
    final public function containsElementEqualTo(mixed $search): bool
    {
        return in_array($search, $this->elements, strict: false);
    }

    /**
     * @param Element $search
     */
    final public function containsElementIdenticalTo(mixed $search): bool
    {
        return in_array($search, $this->elements, strict: true);
    }

    /**
     * @param callable(Element):bool $predicate
     */
    final public function containsElementSatisfying(callable $predicate): bool
    {
        return $this->first($predicate) !== null;
    }

    /**
     * @param callable(Element):bool $predicate
     */
    final public function checkAllElementsSatisfy(callable $predicate): bool
    {
        foreach ($this->elements as $element) {
            if (!$predicate($element)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param callable(Element):bool|null $callback
     * @return ?Element
     */
    final public function first(?callable $callback = null): mixed
    {
        if (!$callback) {
            return $this->elements[0] ?? null;
        }

        foreach ($this->elements as $element) {
            if ($callback($element)) {
                return $element;
            }
        }

        return null;
    }

    /**
     * @param Element $search
     * @return ?int the index of the element in the array if it is found, null otherwise.
     */
    final public function indexOfElementIdenticalTo(mixed $search): ?int
    {
        $index = array_search(needle: $search, haystack: $this->elements, strict: true);

        return $index === false ? null : $index;
    }

    /**
     * @param Element $search
     * @return ?int the index of the element in the array if it is found, null otherwise.
     */
    final public function indexOfElementEqualTo(mixed $search): ?int
    {
        $index = array_search(needle: $search, haystack: $this->elements, strict: false);

        return $index === false ? null : $index;
    }

    /**
     * Returns a new instance with all duplicates removed.
     *
     * Works like array_unique.
     *
     * @param int $flags See PHP method array_unique() for a documentation
     * @see array_unique()
     */
    final public function deduplicate(int $flags = SORT_STRING): static
    {
        return new static(array_values(array_unique($this->elements, $flags)));
    }

    /**
     * Returns a new instance with all elements sorted by the given callback.
     *
     * @param callable(Element, Element):int|null $callback
     * @return static
     * @see usort()
     */
    final public function sorted(?callable $callback = null): self
    {
        if ($callback === null) {
            $callback = fn($lhs, $rhs) => $lhs <=> $rhs;
        }

        // copy elements to not change self
        $elements = $this->elements;
        usort($elements, $callback);

        return new static($elements);
    }

    /**
     * @template MappedElement
     * @template Return of self
     * @param callable(Element):(MappedElement[]|self<MappedElement>) $callback
     * @param ?class-string<Return> $returnType With this you can change the type of the returned collection
     *      if you have an explicit implementation for ImmutableCollection<MappedElement>
     * @return ($returnType is null ? self<MappedElement> : Return)
     */
    final public function flatMap(callable $callback, ?string $returnType = null): self
    {
        $returnType ??= self::class;
        if (!is_a($returnType, self::class, allow_string: true)) {
            throw new InvalidArgumentException(
                sprintf('Passed return type %s does not inherit from %s.', $returnType, self::class),
            );
        }

        $arrays = $this
            ->map($callback)
            ->map(fn(self|array $selfOrArray) => $selfOrArray instanceof self ? $selfOrArray->asArray() : $selfOrArray)
            ->elements;

        return new $returnType(array_merge(...$arrays));
    }

    final public function forEach(callable $closure): void
    {
        foreach ($this->elements as $element) {
            $closure($element);
        }
    }

    /**
     * Applies a callback to each element of the collection and removes all null values afterward.
     *
     * @template MappedElement
     * @template Return of self
     * @param callable(Element):(MappedElement|null) $callback
     * @param ?class-string<Return> $returnType With this you can change the type of the returned collection
     *      if you have an explicit implementation for ImmutableCollection<MappedElement>
     * @return ($returnType is null ? self<MappedElement> : Return)
     */
    final public function compactMap(callable $callback, ?string $returnType = null): self
    {
        $returnType ??= self::class;

        return $this->map($callback, $returnType)->filter(fn($element) => $element !== null);
    }

    /**
     * @template Key of (int|string)
     * @template AggregatedValue
     * @param callable(Element):Key $groupKeyProvider
     * @param callable(ImmutableCollection<Element>):AggregatedValue|null $aggregator
     * @return ($aggregator is null ? array<Key, static<Element>> : array<Key, AggregatedValue>)
     */
    public function groupBy(callable $groupKeyProvider, ?callable $aggregator = null): array
    {
        $groups = [];
        foreach ($this->elements as $element) {
            $groupKey = $groupKeyProvider($element);
            $groups[$groupKey][] = $element;
        }

        if ($aggregator === null) {
            $aggregator = fn(mixed $value) => $value;
        }

        foreach ($groups as $groupKey => $groupElements) {
            $groups[$groupKey] = $aggregator(new static($groupElements));
        }

        return $groups;
    }

    /**
     * This is like PHP's array_diff, but with a strict comparison.
     *
     * @param self<Element> $other
     * @return self<Element>
     */
    final public function getElementsNotIdenticallyContainedIn(self $other): self
    {
        return $this->filter(fn($element) => !$other->containsElementIdenticalTo($element));
    }

    /**
     * @return Element|null
     */
    final public function minimum(): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }

        return min($this->elements);
    }
}
