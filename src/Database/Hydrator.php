<?php

declare(strict_types=1);

namespace Meocox\Database;

use BackedEnum;

/**
 * 极速双向数据类型转换与白名单安全清洗器。
 * 核心采用倒置循环 (Inverted Loop) 与原生 match 表达式，微秒级处理。
 */
final class Hydrator
{
    /**
     * 入库前准备：白名单过滤未知字段 + 类型序列化（如 JSON、布尔转 1/0、枚举转标量）。
     *
     * @param array<string, mixed> $attributes 待入库数据
     * @param array<string, string> $casts 类型转换映射规则
     * @param list<string>|null $whitelist 允许入库的有效字段列表
     * @return array<string, mixed>
     */
    public static function prepareForStorage(
        array $attributes,
        array $casts = [],
        ?array $whitelist = null
    ): array {
        // 1. 白名单过滤，坚决剔除非法或未知字段
        if ($whitelist !== null) {
            $attributes = array_intersect_key($attributes, array_flip($whitelist));
        }

        // 2. 针对指定 Cast 规则字段执行入库前转换
        foreach ($casts as $field => $rule) {
            if (!array_key_exists($field, $attributes)) {
                continue;
            }

            $value = $attributes[$field];
            if ($value === null) {
                continue;
            }

            // 提取基础规则类型
            $baseRule = $rule;
            $arg = null;
            if (str_contains($rule, ':')) {
                [$baseRule, $arg] = explode(':', $rule, 2);
            }

            $attributes[$field] = match ($baseRule) {
                'bool', 'boolean' => $value ? 1 : 0,
                'json', 'array' => is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'decimal' => (string) $value,
                'enum' => $value instanceof BackedEnum ? $value->value : $value,
                'wp_serialize' => is_string($value) ? $value : serialize($value),
                default => $value,
            };
        }

        return $attributes;
    }

    /**
     * 出库后处理：从底层数据库读出的原始数据转换为业务强类型对象/数组。
     *
     * @param array<string, mixed> $row 数据库原始行记录
     * @param array<string, string> $casts 类型转换映射规则
     * @return array<string, mixed>
     */
    public static function castOutbound(array $row, array $casts = []): array
    {
        foreach ($casts as $field => $rule) {
            if (!array_key_exists($field, $row)) {
                continue;
            }

            $value = $row[$field];
            if ($value === null) {
                continue;
            }

            $baseRule = $rule;
            $arg = null;
            if (str_contains($rule, ':')) {
                [$baseRule, $arg] = explode(':', $rule, 2);
            }

            $row[$field] = match ($baseRule) {
                'int', 'integer' => (int) $value,
                'float', 'double' => (float) $value,
                'string' => (string) $value,
                'bool', 'boolean' => (bool) $value,
                'json', 'array' => is_string($value) ? (json_decode($value, true) ?? []) : (array) $value,
                'decimal' => (string) $value, // 坚决保持 string，杜绝浮点精度丢失
                'enum' => ($arg !== null && enum_exists($arg)) ? ($arg::tryFrom($value) ?? $value) : $value,
                'wp_serialize' => is_string($value) && (str_starts_with($value, 'a:') || str_starts_with($value, 'O:') || str_starts_with($value, 's:') || str_starts_with($value, 'b:'))
                    ? @unserialize($value)
                    : $value,
                default => $value,
            };
        }

        return $row;
    }
}
