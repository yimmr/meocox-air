<?php

declare(strict_types=1);

namespace Meocox\Database;

use Meocox\Config;
use ReflectionClass;

/**
 * 零运行时开销的 JIT Schema 编译器与 OPcache 缓存管理器。
 */
final class SchemaManager
{
    private static array $memoryCache = [];

    /**
     * 获取指定模型类的编译后元数据。
     *
     * @param class-string<Model> $modelClass
     * @return array{
     *     primaryKey: string,
     *     columns: array<string, array>,
     *     casts: array<string, string>,
     *     whitelist: list<string>,
     * }
     */
    public static function getMetadata(string $modelClass): array
    {
        if (isset(self::$memoryCache[$modelClass])) {
            return self::$memoryCache[$modelClass];
        }

        $runtimeDir = Config::get('paths.runtime', dirname(__DIR__, 2) . '/runtime');
        $schemaDir = rtrim($runtimeDir, '/\\') . '/schema';

        if (!is_dir($schemaDir) && !@mkdir($schemaDir, 0755, true) && !is_dir($schemaDir)) {
            // 目录创建失败时直接即时计算返回（回退方案）
            return self::compileInMemory($modelClass);
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_]/', '_', $modelClass);
        $cacheFile = $schemaDir . '/' . $safeName . '.php';

        $isDebug = Config::get('debug', false);

        if (is_file($cacheFile)) {
            if ($isDebug) {
                $reflector = new ReflectionClass($modelClass);
                $modelMtime = filemtime((string) $reflector->getFileName());
                $cacheMtime = filemtime($cacheFile);

                if ($modelMtime !== false && $cacheMtime !== false && $modelMtime <= $cacheMtime) {
                    return self::$memoryCache[$modelClass] = require $cacheFile;
                }
            } else {
                return self::$memoryCache[$modelClass] = require $cacheFile;
            }
        }

        // 重新编译并写入持久化缓存
        $meta = self::compileInMemory($modelClass);
        self::atomicWrite($cacheFile, $meta);

        return self::$memoryCache[$modelClass] = $meta;
    }

    /**
     * 内存中即时计算模型表结构元数据。
     */
    private static function compileInMemory(string $modelClass): array
    {
        $table = new Table();

        if (method_exists($modelClass, 'schema')) {
            $modelClass::schema($table);
        }

        $columns = $table->getColumns();
        $primaryKey = $table->getPrimaryKey() ?? 'id';
        $casts = $table->getCasts();
        $whitelist = array_keys($columns);

        return [
            'primaryKey' => $primaryKey,
            'columns' => $columns,
            'casts' => $casts,
            'whitelist' => $whitelist,
        ];
    }

    /**
     * 原子写入编译好的 PHP 文件，由 OPcache 共享内存原生驻留。
     */
    private static function atomicWrite(string $file, array $data): void
    {
        $dir = dirname($file);
        $tempFile = tempnam($dir, 'schema_jit_');
        if ($tempFile === false) {
            return;
        }

        $code = "<?php\n\n// Meocox JIT Compiled Schema - DO NOT EDIT MANUALLY\nreturn " . var_export($data, true) . ";\n";

        if (file_put_contents($tempFile, $code, LOCK_EX) !== false) {
            rename($tempFile, $file);
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($file, true);
            }
        } else {
            @unlink($tempFile);
        }
    }

    /**
     * 清理内存缓存（用于单元测试）。
     */
    public static function clear(): void
    {
        self::$memoryCache = [];
    }
}
