<?php

declare(strict_types=1);

namespace Meocox\Utils;

/**
 * 字符串实用工具库。
 */
final class Str
{
    /**
     * 将字符串转为驼峰命名 (camelCase)。
     */
    public static function camel(string $value): string
    {
        return lcfirst(self::studly($value));
    }

    /**
     * 将字符串转为大驼峰命名 (StudlyCase)。
     */
    public static function studly(string $value): string
    {
        $words = explode(' ', str_replace(['-', '_'], ' ', $value));
        $studlyWords = array_map(static fn(string $word) => ucfirst($word), $words);

        return implode('', $studlyWords);
    }

    /**
     * 将字符串转为蛇形命名 (snake_case)。
     */
    public static function snake(string $value, string $delimiter = '_'): string
    {
        $key = $value;
        if (!ctype_lower($value)) {
            $value = preg_replace('/\s+/u', '', $value) ?? $value;
            $value = preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value) ?? $value;
            return mb_strtolower($value, 'UTF-8');
        }

        return $value;
    }

    /**
     * 判断字符串是否以指定子串开头。
     */
    public static function startsWith(string $haystack, string|iterable $needles): bool
    {
        if (is_string($needles)) {
            return str_starts_with($haystack, $needles);
        }

        foreach ($needles as $needle) {
            if ($needle !== '' && str_starts_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 判断字符串是否以指定子串结尾。
     */
    public static function endsWith(string $haystack, string|iterable $needles): bool
    {
        if (is_string($needles)) {
            return str_ends_with($haystack, $needles);
        }

        foreach ($needles as $needle) {
            if ($needle !== '' && str_ends_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 判断字符串是否包含指定子串。
     */
    public static function contains(string $haystack, string|iterable $needles): bool
    {
        if (is_string($needles)) {
            return str_contains($haystack, $needles);
        }

        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 确保字符串以指定前缀开头。
     */
    public static function start(string $value, string $prefix): string
    {
        return str_starts_with($value, $prefix) ? $value : $prefix . $value;
    }

    /**
     * 确保字符串以指定后缀结尾。
     */
    public static function finish(string $value, string $cap): string
    {
        return str_ends_with($value, $cap) ? $value : $value . $cap;
    }

    /**
     * 生成安全随机字符串。
     */
    public static function random(int $length = 16): string
    {
        $string = '';
        while (($len = strlen($string)) < $length) {
            $size = $length - $len;
            $bytes = random_bytes($size);
            $string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
        }

        return $string;
    }

    /**
     * 生成 UUID v4。
     */
    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * 简单的英文复数化（用于模型默认表名推断）。
     */
    public static function plural(string $value): string
    {
        if (str_ends_with($value, 'y') && !preg_match('/[aeiou]y$/i', $value)) {
            return substr($value, 0, -1) . 'ies';
        }

        if (preg_match('/(s|x|z|ch|sh)$/i', $value)) {
            return $value . 'es';
        }

        return $value . 's';
    }
}
