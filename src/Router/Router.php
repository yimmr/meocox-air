<?php

declare(strict_types=1);

namespace Meocox\Router;

use Meocox\Config;
use Meocox\Exceptions\NotFoundException;
use Meocox\Http\Request;
use Meocox\Http\Response;
use Throwable;

/**
 * 基于约定优于配置的文件路由核心分发器。
 */
final class Router
{
    private string $routesDir;

    public function __construct(?string $routesDir = null)
    {
        if ($routesDir !== null) {
            $this->routesDir = $routesDir;
            return;
        }

        $configured = Config::get('paths.routes');
        if ($configured !== null) {
            $this->routesDir = (string) $configured;
            return;
        }

        $base = defined('AIR_ROOT') ? (string) constant('AIR_ROOT') : getcwd();
        // 优先检查 app/ 目录是否存在（Next.js 16 标准规范）
        if (is_dir($base . '/app') && (is_file($base . '/app/page.php') || is_file($base . '/app/layout.php'))) {
            $this->routesDir = $base . '/app';
        } elseif (is_dir($base . '/app/routes')) {
            $this->routesDir = $base . '/app/routes';
        } elseif (is_dir($base . '/app')) {
            $this->routesDir = $base . '/app';
        } else {
            $this->routesDir = $base . '/app/routes';
        }
    }

    /**
     * 分发 HTTP 请求至目标路由文件。
     */
    public function dispatch(Request $request): Response
    {
        // 0. 优先拦截 Suspense 独立组件请求 (_air_component / X-MeocoxAir-Suspense)
        $componentName = $request->query('_air_component');
        if ($componentName !== null) {
            $viewFile = ViewRenderer::findComponentView((string) $componentName);
            if ($viewFile !== null) {
                $renderer = new ViewRenderer($request->all(), $request);
                return Response::html($renderer->render($viewFile));
            }
            return Response::html('<div style="color: #ef4444; padding: 1rem; border: 1px solid #ef4444;">Component not found: ' . htmlspecialchars((string) $componentName) . '</div>', 404);
        }

        $matched = RouteMatcher::match($this->routesDir, $request->path());

        if ($matched === null) {
            return $this->handleNotFound($request);
        }

        // 绑定解析出的动态参数
        $request->setParams($matched['params']);

        // 1. 严格互斥检查：同一目录禁止同时存在 route.php 与 page.php (Next.js 规范)
        $matchedDir = dirname($matched['file']);
        if (is_file($matchedDir . '/route.php') && is_file($matchedDir . '/page.php')) {
            throw new \LogicException("Meocox Air: Conflict detected in directory [{$matchedDir}]. A route directory cannot contain both route.php and page.php (Next.js convention).");
        }

        // 2. 根据路由类型分发
        try {
            if ($matched['type'] === 'api') {
                return $this->handleApiRoute($matched['file'], $request);
            }

            return $this->handlePageRoute($matched, $request);
        } catch (Throwable $e) {
            return $this->handleErrorBoundary($matched['directories'], $request, $e);
        }
    }

    /**
     * 处理 BFF / API 路由端点 (route.php)。
     */
    private function handleApiRoute(string $file, Request $request): Response
    {
        $handler = require $file;
        $method = $request->method();

        // 方式 1: route.php 返回包含 HTTP 方法的数组 ['GET' => fn(), 'POST' => fn()]
        if (is_array($handler)) {
            if (isset($handler[$method]) && is_callable($handler[$method])) {
                $res = $handler[$method]($request);
                return $this->wrapApiResponse($res);
            }
            return Response::json(['error' => 'Method Not Allowed'], 405);
        }

        // 方式 2: route.php 直接返回一个统一处理闭包
        if (is_callable($handler)) {
            $res = $handler($request);
            return $this->wrapApiResponse($res);
        }

        return Response::json(['error' => 'Invalid Route Handler'], 500);
    }

    /**
     * 包装 API 返回值。
     */
    private function wrapApiResponse(mixed $res): Response
    {
        if ($res instanceof Response) {
            return $res;
        }

        if (is_array($res) || is_object($res)) {
            return Response::json($res);
        }

        return Response::text((string) $res);
    }

    /**
     * 处理页面渲染端点 (page.php)、loader.php 页面前置与自顶向下流式流水线。
     */
    private function handlePageRoute(array $matched, Request $request): Response
    {
        $isPageTarget = $request->header('X-MeocoxAir-Target') === 'page'
            || $request->query('_air_target') === 'page';

        $dir = dirname($matched['file']);

        // 1. 优先执行页面专属前置加载器 loader.php (支持权限检查、查库、提前 redirect 或返回数据)
        $loaderFile = $dir . '/loader.php';
        $loaderData = [];
        if (is_file($loaderFile)) {
            $loaderResult = (static function (string $__file, Request $request): mixed {
                $loaderHandler = require $__file;
                return is_callable($loaderHandler) ? $loaderHandler($request) : $loaderHandler;
            })($loaderFile, $request);

            if ($loaderResult instanceof Response) {
                return $loaderResult; // 提前短路响应或重定向，后续母版 0 渲染
            }
            if (is_array($loaderResult)) {
                $loaderData = $loaderResult;
            }
        }

        // 若是异步追赶 X-MeocoxAir-Target: page，直接返回纯净的 pageContent，跳过所有外层母版！
        if ($isPageTarget) {
            $renderer = new ViewRenderer($loaderData, $request);
            return Response::html($renderer->render($matched['file']));
        }

        $loadingFile = $dir . '/loading.php';
        // 若是首次加载且该路由定义了 loading.php，首屏输出骨架并由微运行时追赶拉取
        if (is_file($loadingFile)) {
            $loadingRenderer = new ViewRenderer($loaderData, $request);
            $loadingContent = $loadingRenderer->render($loadingFile);

            $loadingHtml = sprintf(
                '<div data-air-suspense="%s" data-air-target="page">%s</div>',
                htmlspecialchars($request->path(), ENT_QUOTES, 'UTF-8'),
                $loadingContent
            );

            if ($loadingRenderer->isStandalone()) {
                return Response::html($loadingHtml);
            }

            $layouts = LayoutResolver::resolve($request->path(), $matched['directories']);
            return Response::html($this->renderWithLayouts($layouts, $loadingHtml, $loaderData, $request));
        }

        // 2. 正常页面自顶向下流水线直接推进 (Top-Down Direct Require Pipeline)
        $layouts = LayoutResolver::resolve($request->path(), $matched['directories']);
        $pipeline = array_merge($layouts, [$matched['file']]);

        return Response::html($this->executePipeline($pipeline, $loaderData, $request));
    }

    /**
     * 运行自顶向下母版推进流水线（层与层之间 0 缓冲，原生 require 直出）。
     */
    private function executePipeline(array $pipeline, array $data = [], ?Request $request = null): string
    {
        \Meocox\View::setPipeline($pipeline, $data, $request);

        ob_start();
        try {
            \Meocox\View::slot();
            return (string) ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        } finally {
            \Meocox\View::setPipeline([]);
        }
    }

    /**
     * 将预渲染内容（如 loading 骨架或 error 面板）套入母版链。
     */
    private function renderWithLayouts(array $layouts, string $content, array $viewData = [], ?Request $request = null): string
    {
        if (empty($layouts)) {
            return $content;
        }

        $currentHtml = $content;
        // 母版链由外向内，包装时由内向外依次包裹
        foreach (array_reverse($layouts) as $layoutPath) {
            $layoutRenderer = new ViewRenderer($viewData, $request);
            $layoutRenderer->setSlotContent($currentHtml);
            $currentHtml = $layoutRenderer->render($layoutPath);

            if ($layoutRenderer->isStandalone()) {
                break;
            }
        }

        return $currentHtml;
    }

    /**
     * 逐级向上查找局部 not-found.php 或 error.php 边界，并将错误视图嵌套进外层母版保留布局。
     */
    private function handleErrorBoundary(array $directories, Request $request, Throwable $e): Response
    {
        $isNotFound = $e instanceof NotFoundException || $e->getCode() === 404;
        $dirsReversed = array_reverse($directories);

        // 若为 404 错误，优先逐级向上查找最近的 not-found.php
        if ($isNotFound) {
            foreach ($dirsReversed as $dir) {
                $notFoundFile = $dir . '/not-found.php';
                if (is_file($notFoundFile)) {
                    $renderer = new ViewRenderer(['error' => $e], $request);
                    $content = $renderer->render($notFoundFile);

                    $layouts = LayoutResolver::resolve($request->path(), $directories);
                    $fullHtml = $this->renderWithLayouts($layouts, $content, ['error' => $e], $request);
                    return Response::html($fullHtml, 404);
                }
            }
        }

        // 查找局部通用 error.php 边界
        foreach ($dirsReversed as $dir) {
            $errorFile = $dir . '/error.php';
            if (is_file($errorFile)) {
                $renderer = new ViewRenderer(['error' => $e], $request);
                $content = $renderer->render($errorFile);

                // 嵌套进外层母版列表中输出，保持 Header / Sidebar 依然可用
                $layouts = LayoutResolver::resolve($request->path(), $directories);
                $fullHtml = $this->renderWithLayouts($layouts, $content, ['error' => $e], $request);
                return Response::html($fullHtml, 500);
            }
        }

        throw $e;
    }

    /**
     * 处理 404 资源未找到（支持从当前请求路径各级子目录逐级向上级联查找）。
     */
    private function handleNotFound(Request $request): Response
    {
        $cleanPath = trim($request->path(), '/');
        $segments = $cleanPath === '' ? [] : explode('/', $cleanPath);

        $dirChain = [$this->routesDir];
        $curr = $this->routesDir;
        foreach ($segments as $seg) {
            $nextDir = $curr . '/' . $seg;
            if (is_dir($nextDir)) {
                $dirChain[] = $nextDir;
                $curr = $nextDir;
            } else {
                break;
            }
        }

        $dirsReversed = array_reverse($dirChain);
        foreach ($dirsReversed as $dir) {
            $notFoundFile = $dir . '/not-found.php';
            if (is_file($notFoundFile)) {
                $renderer = new ViewRenderer([], $request);
                $content = $renderer->render($notFoundFile);

                $layouts = LayoutResolver::resolve($request->path(), $dirChain);
                $fullHtml = $this->renderWithLayouts($layouts, $content, [], $request);
                return Response::html($fullHtml, 404);
            }
        }

        throw new NotFoundException("Route not found: {$request->path()}");
    }
}
