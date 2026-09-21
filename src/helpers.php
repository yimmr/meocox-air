<?php

declare(strict_types=1);

use Meocox\View;

if (!function_exists('component')) {
    /**
     * 直接渲染并输出组件（0 ob 缓冲开销）。
     *
     * @param string $name 组件名称
     * @param array<string, mixed> $props 传递给组件的属性数组
     */
    function component(string $name, array $props = []): void
    {
        View::component($name, $props);
    }
}

if (!function_exists('componentHTML')) {
    /**
     * 渲染组件并返回 HTML 字符串。
     *
     * @param string $name 组件名称
     * @param array<string, mixed> $props 传递给组件的属性数组
     * @return string
     */
    function componentHTML(string $name, array $props = []): string
    {
        return View::componentHTML($name, $props);
    }
}

if (!function_exists('slot')) {
    /**
     * 获取母版布局中的子页面插槽内容。
     */
    function slot(): string
    {
        return View::slot();
    }
}

if (!function_exists('suspense')) {
    /**
     * 声明异步挂起占位容器（对标 Next.js / React <Suspense fallback={...}>）。
     *
     * @param string|callable $component 目标组件名称或闭包
     * @param mixed $fallback 可选骨架屏占位
     * @param array<string, mixed> $props 传递给组件的业务参数
     */
    function suspense(string|callable $component, mixed $fallback = null, array $props = []): string
    {
        return View::suspense($component, $fallback, $props);
    }
}
