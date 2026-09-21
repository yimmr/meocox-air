<?php

declare(strict_types=1);

namespace Meocox\Router;

use Meocox\Http\Request;

/**
 * 沙箱插槽视图渲染器。
 */
final class ViewRenderer
{
    private string $slotContent = '';
    private bool $isStandalone = false;
    private array $data = [];
    private ?Request $request = null;

    public function __construct(array $data = [], ?Request $request = null)
    {
        $this->data = $data;
        $this->request = $request ?? (class_exists(\Meocox\Air::class) ? \Meocox\Air::request() : null);
    }

    /**
     * 设置子插槽 HTML 内容。
     */
    public function setSlotContent(string $content): self
    {
        $this->slotContent = $content;
        return $this;
    }

    /**
     * 在母版中输出子页面内容。
     */
    public function slot(): string
    {
        if (\Meocox\View::hasPipeline()) {
            \Meocox\View::slot();
            return '';
        }

        return $this->slotContent;
    }

    /**
     * 声明当前页面为独立页面，跳出外层母版包裹。
     */
    public function standalone(): self
    {
        $this->isStandalone = true;
        return $this;
    }

    /**
     * 检查是否跳出母版。
     */
    public function isStandalone(): bool
    {
        return $this->isStandalone;
    }

    /**
     * 获取请求对象。
     */
    public function request(): ?Request
    {
        return $this->request;
    }

    /**
     * 获取视图传递的数据。
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * HTML 特殊字符转义输出。
     */
    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * 渲染指定的 PHP 模板文件。
     */
    public function render(string $templatePath): string
    {
        if (!is_file($templatePath)) {
            return '';
        }

        extract($this->data, EXTR_SKIP);

        \Meocox\View::pushRenderer($this);
        ob_start();
        try {
            require $templatePath;
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        } finally {
            \Meocox\View::popRenderer();
        }
    }

    /**
     * 声明一个异步挂起占位区域（对标 Next.js / React <Suspense fallback={...}>）。
     *
     * @param string|callable $component 目标组件（组件名称或渲染闭包）
     * @param mixed $fallback 可选骨架屏占位（文件路径、闭包或纯 HTML；为空时自动推导 skeleton.php）
     * @param array<string, mixed> $props 传递给组件的业务参数
     */
    public function suspense(string|callable $component, mixed $fallback = null, array $props = []): string
    {
        $componentName = is_string($component) ? $component : 'inline_' . substr(md5(uniqid()), 0, 8);

        // 解析 fallback 骨架屏内容
        $fallbackHtml = '';
        if ($fallback === null && is_string($component)) {
            $fallbackFile = self::findComponentSkeleton($component);
            if ($fallbackFile !== null) {
                $fallbackHtml = $this->render($fallbackFile);
            }
        } elseif (is_string($fallback) && is_file($fallback)) {
            $fallbackHtml = $this->render($fallback);
        } elseif (is_callable($fallback)) {
            $fallbackHtml = (string) $fallback($props);
        } elseif (is_string($fallback)) {
            $fallbackHtml = (string) $fallback;
        }

        // 构造异步请求 URL（传递 _air_component 与 props）
        $queryParams = array_merge(['_air_component' => $componentName], $props);
        $url = '?' . http_build_query($queryParams);

        return sprintf(
            '<div data-air-suspense="%s" data-air-component="%s">%s</div>',
            $this->escape($url),
            $this->escape($componentName),
            $fallbackHtml
        );
    }

    /**
     * 获取当前项目的根物理路径。
     */
    public static function getProjectRoot(): string
    {
        if (defined('AIR_ROOT')) {
            return (string) constant('AIR_ROOT');
        }

        if (class_exists(\Meocox\Config::class)) {
            $routesPath = \Meocox\Config::get('paths.routes');
            if ($routesPath) {
                return dirname((string) $routesPath);
            }
        }

        $cwd = getcwd() ?: '.';
        if (is_dir($cwd . '/src')) {
            return $cwd;
        }
        if (is_dir(dirname($cwd) . '/src')) {
            return dirname($cwd);
        }

        return $cwd;
    }

    /**
     * 查找组件的骨架屏文件 (skeleton.php)。
     */
    public static function findComponentSkeleton(string $name): ?string
    {
        $base = self::getProjectRoot();
        $candidates = [
            $base . '/src/widgets/' . $name . '/skeleton.php',
            $base . '/src/components/' . $name . '/skeleton.php',
            $base . '/src/widgets/' . $name . '.skeleton.php',
            $base . '/src/components/' . $name . '.skeleton.php',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * 查找组件的主视图文件 (view.php)。
     */
    public static function findComponentView(string $name): ?string
    {
        $base = self::getProjectRoot();
        $candidates = [
            $base . '/src/widgets/' . $name . '/view.php',
            $base . '/src/components/' . $name . '/view.php',
            $base . '/src/widgets/' . $name . '.php',
            $base . '/src/components/' . $name . '.php',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }
}
