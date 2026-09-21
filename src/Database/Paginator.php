<?php

declare(strict_types=1);

namespace Meocox\Database;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * 分页数据容器。
 */
class Paginator implements JsonSerializable, IteratorAggregate, Countable
{
    public function __construct(
        protected array $items,
        protected int $total,
        protected int $perPage,
        protected int $currentPage = 1
    ) {
    }

    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return max((int) ceil($this->total / $this->perPage), 1);
    }

    public function hasMore(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function toArray(): array
    {
        return [
            'data' => $this->items,
            'pagination' => [
                'total' => $this->total,
                'perPage' => $this->perPage,
                'currentPage' => $this->currentPage,
                'lastPage' => $this->lastPage(),
                'hasMore' => $this->hasMore(),
            ],
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
