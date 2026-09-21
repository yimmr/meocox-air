<?php

declare(strict_types=1);

namespace Meocox\Router;

/**
 * 基于文件系统的路由路径与动态参数匹配器。
 */
final class RouteMatcher
{
    /**
     * 在路由根目录中搜索匹配目标 URL 的端点文件。
     *
     * @param string $routesDir 路由根目录（如 app/routes 或 src/Routes）
     * @param string $path 请求路径（如 /users/123）
     * @return array{
     *     type: 'page'|'api',
     *     file: string,
     *     params: array<string, string>,
     *     directories: list<string>
     * }|null
     */
    public static function match(string $routesDir, string $path): ?array
    {
        $routesDir = rtrim($routesDir, '/\\');
        if (!is_dir($routesDir)) {
            return null;
        }

        $cleanPath = trim($path, '/');
        $segments = $cleanPath === '' ? [] : explode('/', $cleanPath);

        return self::resolve($routesDir, $segments, [$routesDir], []);
    }

    /**
     * 深度优先遍历目录匹配。
     */
    private static function resolve(
        string $currentDir,
        array $segments,
        array $dirChain,
        array $params
    ): ?array {
        // 达到路径末端：检查当前目录下的 page.php 或 route.php
        if (empty($segments)) {
            if (is_file($currentDir . '/route.php')) {
                return [
                    'type' => 'api',
                    'file' => $currentDir . '/route.php',
                    'params' => $params,
                    'directories' => $dirChain,
                ];
            }

            if (is_file($currentDir . '/page.php')) {
                return [
                    'type' => 'page',
                    'file' => $currentDir . '/page.php',
                    'params' => $params,
                    'directories' => $dirChain,
                ];
            }

            return null;
        }

        $segment = array_shift($segments);

        // 1. 尝试精确目录匹配
        $exactDir = $currentDir . '/' . $segment;
        if (is_dir($exactDir) && !str_starts_with($segment, '_')) {
            $matched = self::resolve($exactDir, $segments, [...$dirChain, $exactDir], $params);
            if ($matched !== null) {
                return $matched;
            }
        }

        // 2. 尝试子目录扫描（寻找动态参数 [param] 或 [...catchAll]）
        $items = scandir($currentDir);
        if ($items !== false) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..' || str_starts_with($item, '_')) {
                    continue;
                }

                $itemPath = $currentDir . '/' . $item;
                if (!is_dir($itemPath)) {
                    continue;
                }

                // 2.1 捕获所有参数 [...slug]
                if (str_starts_with($item, '[...') && str_ends_with($item, ']')) {
                    $paramName = substr($item, 4, -1);
                    $catchAll = [$segment, ...$segments];
                    $newParams = array_merge($params, [$paramName => implode('/', $catchAll)]);

                    // 检查当前 catchAll 目录下的 page/route
                    if (is_file($itemPath . '/route.php')) {
                        return [
                            'type' => 'api',
                            'file' => $itemPath . '/route.php',
                            'params' => $newParams,
                            'directories' => [...$dirChain, $itemPath],
                        ];
                    }

                    if (is_file($itemPath . '/page.php')) {
                        return [
                            'type' => 'page',
                            'file' => $itemPath . '/page.php',
                            'params' => $newParams,
                            'directories' => [...$dirChain, $itemPath],
                        ];
                    }
                }

                // 2.2 单段动态参数 [id]
                if (str_starts_with($item, '[') && str_ends_with($item, ']') && !str_starts_with($item, '[...')) {
                    $paramName = substr($item, 1, -1);
                    $newParams = array_merge($params, [$paramName => $segment]);

                    $matched = self::resolve($itemPath, $segments, [...$dirChain, $itemPath], $newParams);
                    if ($matched !== null) {
                        return $matched;
                    }
                }
            }
        }

        return null;
    }
}
