<?php

declare(strict_types=1);

namespace Meocox;

use Meocox\Config\Repository;

/**
 * 全局配置门面。
 */
final class Config
{
    private static ?Repository $repository = null;

    /**
     * 定义配置辅助函数（用于 meocox.config.php 获得完备的 IDE 代码补全）。
     *
     * @param array{
     *     basePath?: string,
     *     routing?: array{
     *         rewrites?: array<string, string>,
     *         redirects?: array<string, array{destination: string, permanent?: bool}>,
     *         headers?: array<string, array<string, string>>,
     *         layouts?: array<string, string|false>,
     *     },
     *     database?: array{
     *         default?: string,
     *         connections?: array<string, array<string, mixed>>,
     *         slowQueryThreshold?: int,
     *     },
     *     paths?: array{
     *         runtime?: string,
     *         routes?: string,
     *         components?: string,
     *     },
     *     aliases?: array<string, class-string|false>|false,
     *     debug?: bool,
     * } $config
     * @return array
     */
    public static function define(array $config): array
    {
        return $config;
    }

    /**
     * 获取或初始化底层配置仓库。
     */
    public static function repository(): Repository
    {
        if (self::$repository === null) {
            self::$repository = new Repository();
        }

        return self::$repository;
    }

    /**
     * 加载配置文件或配置数组。
     */
    public static function load(string|array $config): void
    {
        $repo = self::repository();

        if (is_string($config) && is_file($config)) {
            $data = require $config;
            if (is_array($data)) {
                $repo->merge($data);
            }
        } elseif (is_array($config)) {
            $repo->merge($config);
        }
    }

    /**
     * 读取配置项。
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::repository()->get($key, $default);
    }

    /**
     * 设置配置项。
     */
    public static function set(string $key, mixed $value): void
    {
        self::repository()->set($key, $value);
    }

    /**
     * 检查配置项是否存在。
     */
    public static function has(string $key): bool
    {
        return self::repository()->has($key);
    }

    /**
     * 重置配置仓库（主要用于测试隔离）。
     */
    public static function reset(): void
    {
        self::$repository = null;
    }
}
