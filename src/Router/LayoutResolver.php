<?php

declare(strict_types=1);

namespace Meocox\Router;

use Meocox\Config;

/**
 * 三级母版布局解析器：
 * 1. 优先级 1：meocox.config.php 配置覆写 (routing.layouts)
 * 2. 优先级 2：同级/下级专有命名母版 {segment}.layout.php (替代默认 layout.php，消灭多余虚假目录)
 * 3. 优先级 3：默认级联递归母版 layout.php (自顶向下套娃)
 */
final class LayoutResolver
{
    /**
     * 解析当前匹配路径所需经过的母版链（由内向外排序，用于逐步包装 slot）。
     *
     * @param string $path URL 路径 (如 /auth/login)
     * @param list<string> $dirChain 经过的物理目录链（由顶向下）
     * @return list<string> 母版文件路径列表（从内层到外层）
     */
    public static function resolve(string $path, array $dirChain): array
    {
        // 优先级 1: 检查全局配置规则
        $configLayouts = Config::get('routing.layouts', []);
        foreach ($configLayouts as $pattern => $layoutTarget) {
            $regex = '#^' . str_replace('*', '.*', $pattern) . '$#';
            if (preg_match($regex, $path)) {
                if ($layoutTarget === false || $layoutTarget === null) {
                    // 显式禁用母版
                    return [];
                }
                if (is_string($layoutTarget) && is_file($layoutTarget)) {
                    return [$layoutTarget];
                }
            }
        }

        $collected = [];
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $totalDirs = count($dirChain);

        // 自顶向下遍历目录链
        for ($i = 0; $i < $totalDirs; $i++) {
            $dir = $dirChain[$i];
            $nextSegment = $segments[$i] ?? null;

            // 优先级 2: 检查专有命名母版 {segment}.layout.php
            // 例如在当前目录存在 login.layout.php，当匹配至下一段为 login 时优先替换默认 layout.php
            $namedLayout = null;
            if ($nextSegment !== null) {
                $candidate = $dir . '/' . $nextSegment . '.layout.php';
                if (is_file($candidate)) {
                    $namedLayout = $candidate;
                }
            }

            if ($namedLayout !== null) {
                $collected[] = $namedLayout;
                continue;
            }

            // 优先级 3: 默认级联 layout.php
            $defaultLayout = $dir . '/layout.php';
            if (is_file($defaultLayout)) {
                $collected[] = $defaultLayout;
            }
        }

        // 返回由外向内的列表（供自顶向下流水线直接推进）
        return $collected;
    }
}
