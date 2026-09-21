<?php

declare(strict_types=1);

namespace Meocox\Config;

use ArrayAccess;
use Meocox\Utils\Arr;

/**
 * 只读/读写配置仓库，支持点语法访问。
 */
final class Repository implements ArrayAccess
{
    public function __construct(private array $items = [])
    {
    }

    /**
     * 判断配置项是否存在。
     */
    public function has(string $key): bool
    {
        return Arr::has($this->items, $key);
    }

    /**
     * 获取配置项。
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->items, $key, $default);
    }

    /**
     * 设置配置项。
     */
    public function set(string $key, mixed $value): void
    {
        Arr::set($this->items, $key, $value);
    }

    /**
     * 合并配置数组。
     */
    public function merge(array $items): void
    {
        $this->items = array_replace_recursive($this->items, $items);
    }

    /**
     * 获取全部配置项。
     */
    public function all(): array
    {
        return $this->items;
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        Arr::forget($this->items, (string) $offset);
    }
}
