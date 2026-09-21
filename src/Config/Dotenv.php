<?php

declare(strict_types=1);

namespace Meocox\Config;

/**
 * 零外部依赖的 .env 环境变量解析与加载器。
 */
final class Dotenv
{
    /**
     * 加载指定路径的 .env 文件到环境。
     */
    public static function load(string $filePath): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return [];
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $vars = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // 跳过空行与纯注释
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // 解析 KEY=VALUE
            $parts = explode('=', $line, 2);
            if (count($parts) < 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);

            // 去除双引号或单引号包裹
            if (preg_match('/^"((?:\\\\.|[^"\\\\])*)"(?:\s*#.*)?$/', $value, $matches)) {
                $value = stripcslashes($matches[1]);
            } elseif (preg_match('/^\'([^\']*)\'(?:\s*#.*)?$/', $value, $matches)) {
                $value = $matches[1];
            } else {
                // 无引号值：截断行内注释
                $commentPos = strpos($value, ' #');
                if ($commentPos !== false) {
                    $value = substr($value, 0, $commentPos);
                }
                $value = trim($value);

                // 解析布尔值与空值
                $lower = strtolower($value);
                if ($lower === 'true') {
                    $value = 'true';
                } elseif ($lower === 'false') {
                    $value = 'false';
                } elseif ($lower === 'null') {
                    $value = '';
                }
            }

            // 变量引用展开: ${VAR_NAME}
            $value = preg_replace_callback('/\$\{([a-zA-Z0-9_]+)\}/', static function (array $m) use ($vars): string {
                $varName = $m[1];
                return $vars[$varName] ?? $_ENV[$varName] ?? $_SERVER[$varName] ?? '';
            }, $value);

            $vars[$key] = $value;

            // 注入全局环境
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }

        return $vars;
    }
}
