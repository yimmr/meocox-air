<?php

declare(strict_types=1);

namespace Meocox;

use InvalidArgumentException;
use Meocox\Router\ViewRenderer;
use Throwable;

/**
 * 视图与组件门面（View Facade）。
 * 提供 100% IDE 智能感知的组件调用、插槽获取与挂起容器支持。
 */
final class View
{
    /** @var array<ViewRenderer> 当前活跃的视图渲染器栈 */
    private static array $rendererStack = [];

    /**
     * 压入当前视图渲染器。
     */
    public static function pushRenderer(ViewRenderer $renderer): void
    {
        self::$rendererStack[] = $renderer;
    }

    /**
     * 弹出当前视图渲染器。
     */
    public static function popRenderer(): ?ViewRenderer
    {
        return array_pop(self::$rendererStack);
    }

    /**
     * 获取当前处于活跃顶层的视图渲染器。
     */
    public static function current(): ?ViewRenderer
    {
        $count = count(self::$rendererStack);
        return $count > 0 ? self::$rendererStack[$count - 1] : null;
    }

    /** @var list<string> 自顶向下待执行文件链路 [root.layout.php, sub.layout.php, page.php] */
    private static array $slotPipeline = [];
    /** @var array<string, mixed> 传递给流水线的共享数据 */
    private static array $pipelineData = [];
    /** @var \Meocox\Http\Request|null 传递给流水线的当前请求 */
    private static ?\Meocox\Http\Request $pipelineRequest = null;

    /**
     * 设置自顶向下执行流水线与传递数据。
     *
     * @param list<string> $files
     * @param array<string, mixed> $data
     * @param \Meocox\Http\Request|null $request
     */
    public static function setPipeline(array $files, array $data = [], ?\Meocox\Http\Request $request = null): void
    {
        self::$slotPipeline = array_values($files);
        self::$pipelineData = $data;
        self::$pipelineRequest = $request;
    }

    /**
     * 检查当前是否存在自顶向下待推进的流水线链路。
     */
    public static function hasPipeline(): bool
    {
        return !empty(self::$slotPipeline);
    }

    /**
     * 获取母版布局中的子页面插槽 HTML 内容，或直接原生 require 推进下一层文件。
     */
    public static function slot(): string
    {
        if (!empty(self::$slotPipeline)) {
            $nextFile = array_shift(self::$slotPipeline);
            $data = self::$pipelineData;
            $request = self::$pipelineRequest;
            $renderer = self::current() ?? new ViewRenderer($data, $request);

            self::pushRenderer($renderer);
            try {
                (function (string $__file, array $__data): void {
                    extract($__data, EXTR_SKIP);
                    require $__file;
                })->bindTo($renderer, $renderer)($nextFile, $data);
            } finally {
                self::popRenderer();
            }

            return '';
        }

        $renderer = self::current();
        return $renderer !== null ? $renderer->slot() : '';
    }

    /**
     * 声明异步挂起占位容器（对标 Next.js / React <Suspense fallback={...}>）。
     *
     * @param string|callable $component 目标组件名称或闭包
     * @param mixed $fallback 可选骨架屏占位（文件路径、闭包或纯 HTML）
     * @param array<string, mixed> $props 传递给组件的业务参数
     */
    public static function suspense(string|callable $component, mixed $fallback = null, array $props = []): string
    {
        $renderer = self::current();
        if ($renderer !== null) {
            return $renderer->suspense($component, $fallback, $props);
        }

        return (new ViewRenderer())->suspense($component, $fallback, $props);
    }

    /**
     * HTML 特殊字符转义输出。
     */
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * 直接渲染并输出组件（0 ob 缓冲开销，直接写入父级输出流）。
     *
     * @param string $name 组件名称（支持点号语法如 'ui.badge'，或 'user-card'）
     * @param array<string, mixed> $props 传递给组件的属性数组
     * @throws InvalidArgumentException 当组件文件不存在时抛出
     */
    public static function component(string $name, array $props = []): void
    {
        $file = self::resolveComponent($name);
        if ($file === null) {
            throw new InvalidArgumentException("Meocox Air: Component [{$name}] not found.");
        }

        // 在无 $this 绑定的独立静态闭包中执行，提取 $props 为原生变量，杜绝变量泄漏与 ob 内存分配
        (static function (string $__file, array $props): void {
            extract($props, EXTR_SKIP);
            require $__file;
        })($file, $props);
    }

    /**
     * 渲染组件并以 HTML 字符串返回（使用输出缓冲捕获）。
     * 带有异常安全清理保障，执行失败时自动清空残缺缓冲并重抛异常。
     *
     * @param string $name 组件名称
     * @param array<string, mixed> $props 传递给组件的属性数组
     * @return string 渲染后的 HTML 字符串
     * @throws Throwable
     */
    public static function componentHTML(string $name, array $props = []): string
    {
        ob_start();
        try {
            self::component($name, $props);
            return (string) ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    /**
     * 检查组件文件是否存在。
     */
    public static function exists(string $name): bool
    {
        return self::resolveComponent($name) !== null;
    }

    /**
     * 解析组件真实物理文件路径。
     */
    public static function resolveComponent(string $name): ?string
    {
        // 若直接传入已存在的物理绝对/相对文件路径
        if (is_file($name)) {
            return $name;
        }

        // 统一处理层级分隔符（支持 'ui.button' -> 'ui/button'）
        $normalized = str_replace('.', '/', trim($name, '/'));

        $candidates = [];

        // 1. 用户自定义配置组件根目录（paths.components）
        $configuredPath = Config::get('paths.components');
        if (is_string($configuredPath) && is_dir($configuredPath)) {
            $base = rtrim($configuredPath, '/');
            $candidates[] = "{$base}/{$normalized}.php";
            $candidates[] = "{$base}/{$normalized}/view.php";
            $candidates[] = "{$base}/{$normalized}/index.php";
        }

        // 2. 项目约定默认目录（按优先级探查）
        $root = ViewRenderer::getProjectRoot();
        $defaultBases = [
            "{$root}/src/components",
            "{$root}/components",
            "{$root}/src/widgets",
            "{$root}/widgets",
        ];

        foreach ($defaultBases as $base) {
            $candidates[] = "{$base}/{$normalized}.php";
            $candidates[] = "{$base}/{$normalized}/view.php";
            $candidates[] = "{$base}/{$normalized}/index.php";
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * 清理所有状态（主要用于测试重置）。
     */
    public static function reset(): void
    {
        self::$rendererStack = [];
        self::$slotPipeline = [];
        self::$pipelineData = [];
        self::$pipelineRequest = null;
    }
}
