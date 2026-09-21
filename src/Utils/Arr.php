<?php

declare(strict_types=1);

namespace Meocox\Utils;

use ArrayAccess;

/**
 * 数组实用工具库。
 */
final class Arr
{
    /**
     * 判断数组是否为关联数组。
     */
    public static function isAssoc(array $array): bool
    {
        return !array_is_list($array);
    }

    /**
     * 使用点语法从多维数组中获取值。
     */
    public static function get(array|ArrayAccess $array, string|int|null $key, mixed $default = null): mixed
    {
        if ($key === null) {
            return $array;
        }

        if (isset($array[$key])) {
            return $array[$key];
        }

        if (!str_contains((string) $key, '.')) {
            return $array[$key] ?? $default;
        }

        $segments = explode('.', (string) $key);
        $target = $array;

        foreach ($segments as $segment) {
            if ((is_array($target) || $target instanceof ArrayAccess) && isset($target[$segment])) {
                $target = $target[$segment];
            } else {
                return $default;
            }
        }

        return $target;
    }

    /**
     * 使用点语法设置多维数组中的值。
     */
    public static function set(array &$array, string|int|null $key, mixed $value): array
    {
        if ($key === null) {
            return $array = $value;
        }

        $keys = explode('.', (string) $key);
        $current = &$array;

        while (count($keys) > 1) {
            $k = array_shift($keys);
            if (!isset($current[$k]) || !is_array($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }

        $current[array_shift($keys)] = $value;

        return $array;
    }

    /**
     * 判断点语法键是否存在。
     */
    public static function has(array|ArrayAccess $array, string|int $key): bool
    {
        if (isset($array[$key])) {
            return true;
        }

        $segments = explode('.', (string) $key);
        $target = $array;

        foreach ($segments as $segment) {
            if ((is_array($target) || $target instanceof ArrayAccess) && isset($target[$segment])) {
                $target = $target[$segment];
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * 仅返回指定键组成的子数组。
     */
    public static function only(array $array, array|string $keys): array
    {
        $keys = (array) $keys;
        return array_intersect_key($array, array_flip($keys));
    }

    /**
     * 排除指定键后返回子数组。
     */
    public static function except(array $array, array|string $keys): array
    {
        $keys = (array) $keys;
        return array_diff_key($array, array_flip($keys));
    }

    /**
     * 移除多维数组中的键（支持点语法）。
     */
    public static function forget(array &$array, array|string $keys): void
    {
        $keys = (array) $keys;

        foreach ($keys as $key) {
            if (isset($array[$key])) {
                unset($array[$key]);
                continue;
            }

            $segments = explode('.', (string) $key);
            $target = &$array;

            while (count($segments) > 1) {
                $segment = array_shift($segments);
                if (isset($target[$segment]) && is_array($target[$segment])) {
                    $target = &$target[$segment];
                } else {
                    continue 2;
                }
            }

            unset($target[array_shift($segments)]);
        }
    }

    /**
     * 将值包装为数组。
     */
    public static function wrap(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    /**
     * 获取满足回调条件的第一个元素。
     */
    public static function first(iterable $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            foreach ($array as $item) {
                return $item;
            }
            return $default;
        }

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * 获取满足回调条件的最后一个元素。
     */
    public static function last(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        return self::first(array_reverse($array, true), $callback, $default);
    }
}
