<?php

declare(strict_types=1);

namespace Meocox;

use Meocox\Exceptions\HttpException;
use Meocox\Http\Pipeline;
use Meocox\Http\Request;
use Meocox\Http\Response;
use Meocox\Router\Router;
use Meocox\Utils\Log;
use Throwable;

require_once __DIR__ . '/helpers.php';

/**
 * Meocox Air 核心生命周期与功能门面。
 */
final class Air
{
    public const VERSION = '0.1.0';

    /** @var array<callable> */
    private static array $afterCallbacks = [];
    private static ?Request $currentRequest = null;
    private static array $middlewares = [];

    /**
     * 获取框架版本号。
     */
    public static function version(): string
    {
        return self::VERSION;
    }

    /**
     * 注册全局中间件。
     */
    public static function use(mixed $middleware): void
    {
        self::$middlewares[] = $middleware;
    }

    /**
     * 注册响应发送后执行的副作用异步任务（类 Next.js 16 after() 特性）。
     * 在 PHP-FPM fastcgi_finish_request() 之后非阻塞执行，不拖慢用户端响应时间。
     */
    public static function after(callable $callback): void
    {
        self::$afterCallbacks[] = $callback;
    }

    /**
     * 获取当前处理中的请求对象。
     */
    public static function request(): Request
    {
        if (self::$currentRequest === null) {
            self::$currentRequest = Request::createFromGlobals();
        }

        return self::$currentRequest;
    }

    /** @var array<string, class-string> 默认核心门面全局别名 */
    private static array $defaultAliases = [
        'Air'    => \Meocox\Air::class,
        'Auth'   => \Meocox\Auth::class,
        'DB'     => \Meocox\DB::class,
        'Config' => \Meocox\Config::class,
        'View'   => \Meocox\View::class,
    ];

    /**
     * 注册全局门面类别名（支持用户配置自定义与多别名映射）。
     */
    public static function registerAliases(): void
    {
        $configured = Config::get('aliases', []);
        if ($configured === false) {
            return;
        }

        $aliases = array_merge(self::$defaultAliases, (array) $configured);

        foreach ($aliases as $alias => $target) {
            if ($target === false || $target === null) {
                continue;
            }

            if (!class_exists($alias, false) && class_exists($target)) {
                class_alias($target, (string) $alias);
            }
        }
    }

    /**
     * 生成供 IDE（PhpStorm、Intelephense、VS Code）静态分析与代码提示的 Helper 文件。
     */
    public static function generateIdeHelper(?string $outputPath = null): string
    {
        $configured = Config::get('aliases', []);
        $aliases = ($configured === false) ? [] : array_merge(self::$defaultAliases, (array) $configured);

        $code = "<?php\n\n";
        $code .= "/**\n";
        $code .= " * Meocox Air IDE Helper for global class aliases.\n";
        $code .= " * Auto-generated for static analysis and IDE autocomplete (PhpStorm / Intelephense).\n";
        $code .= " * Do not include this file at runtime.\n";
        $code .= " */\n\n";
        $code .= "namespace {\n";
        $code .= "    exit('This file should not be included, only analyzed by your IDE');\n\n";

        foreach ($aliases as $alias => $target) {
            if ($target === false || $target === null) {
                continue;
            }
            $target = '\\' . ltrim((string) $target, '\\');
            $code .= "    class {$alias} extends {$target} {}\n";
        }

        $code .= "}\n";

        if ($outputPath !== null) {
            $dir = dirname($outputPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($outputPath, $code);
        }

        return $code;
    }

    /**
     * 处理 HTTP 请求并返回 Response 实例（不直接输出或终止进程，适用于编程调用、测试与常驻容器）。
     */
    public static function handle(?Request $request = null, ?string $routesPath = null): Response
    {
        $request = $request ?? Request::capture();

        // 自动注册全局门面类别名
        self::registerAliases();

        // 0. 应用 proxy.php 全局代理拦截器 (Next.js 16 规范)
        $response = self::handleProxyFile($request, $routesPath);

        // 1. 应用 meocox.config.php 中的 headers / redirects / rewrites
        if ($response === null) {
            $response = self::handleProxyRules($request);
        }

        if ($response === null) {
            try {
                $response = (new Pipeline())
                    ->send($request)
                    ->through(self::$middlewares)
                    ->then(function (Request $req) use ($routesPath): Response {
                        $router = new Router($routesPath ?? Config::get('paths.routes'));
                        return $router->dispatch($req);
                    });
            } catch (HttpException $e) {
                $response = Response::json([
                    'error' => true,
                    'message' => $e->getMessage(),
                ], $e->getStatusCode(), $e->getHeaders());
            } catch (Throwable $e) {
                Log::error('Unhandled Exception: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $isDebug = Config::get('debug', false);
                $response = Response::json([
                    'error' => true,
                    'message' => $isDebug ? $e->getMessage() : 'Internal Server Error',
                    'trace' => $isDebug ? explode("\n", $e->getTraceAsString()) : null,
                ], 500);
            }
        }

        // 若包含 Suspense 异步挂起容器且开启自动注入，在 </body> 前注入微运行时脚本
        return self::injectSuspenseRuntime($response);
    }

    /**
     * 执行 HTTP 完整请求周期：中间件管道 -> 路由分发 -> 响应发送 -> 后台任务调度。
     */
    public static function run(?Request $request = null, ?string $routesPath = null): void
    {
        $response = self::handle($request, $routesPath);

        // 发送响应到客户端
        $response->send();

        // 结束前台响应并执行后置副作用
        self::terminate();
    }

    /**
     * 针对含有 data-air-suspense 的 HTML 响应自动注入极简微运行时脚本（< 400 字节原生 JS）。
     */
    public static function injectSuspenseRuntime(Response $response): Response
    {
        if (!Config::get('defer.autoInjectScript', true)) {
            return $response;
        }

        $content = $response->getContent();
        if (!str_contains($content, 'data-air-suspense') || !str_contains($content, '</body>')) {
            return $response;
        }

        $script = <<<'HTML'
<script id="meocoxair-runtime">
(function(){
  document.querySelectorAll('[data-air-suspense]').forEach(function(el){
    var url = el.getAttribute('data-air-suspense');
    var target = el.getAttribute('data-air-target');
    var headers = {};
    if (target === 'page') {
      headers['X-MeocoxAir-Target'] = 'page';
    } else {
      headers['X-MeocoxAir-Suspense'] = '1';
    }
    fetch(url, { headers: headers }).then(function(res){
      if(res.ok) return res.text();
      throw new Error(res.statusText);
    }).then(function(html){
      el.style.opacity = '0';
      el.style.transition = 'opacity 0.25s ease';
      el.innerHTML = html;
      requestAnimationFrame(function(){ el.style.opacity = '1'; });
      el.dispatchEvent(new CustomEvent('air:loaded', { bubbles: true }));
    }).catch(function(err){
      console.error('[MeocoxAir Suspense Error]', err);
    });
  });
})();
</script>
HTML;

        $newContent = str_replace('</body>', $script . "\n</body>", $content);
        $response->setContent($newContent);

        return $response;
    }

    /**
     * 探测并执行 proxy.php 全局代理拦截文件（Next.js 16 规范）。
     * 支持返回 Response 对象直接中断全站请求。
     */
    public static function handleProxyFile(?Request $request = null, ?string $routesPath = null): ?Response
    {
        $request = $request ?? Request::capture();
        $base = $routesPath ?? Config::get('paths.routes');
        $candidates = [];
        if ($base) {
            $basePath = rtrim((string) $base, '/');
            $candidates[] = $basePath . '/proxy.php';
            $candidates[] = dirname($basePath) . '/proxy.php';
        }
        $root = \Meocox\Router\ViewRenderer::getProjectRoot();
        $candidates[] = $root . '/app/proxy.php';
        $candidates[] = $root . '/proxy.php';

        foreach (array_unique($candidates) as $file) {
            if (is_file($file)) {
                $proxyHandler = require $file;
                $result = is_callable($proxyHandler)
                    ? $proxyHandler($request)
                    : $proxyHandler;

                if ($result instanceof Response) {
                    return $result;
                }
                break;
            }
        }

        return null;
    }

    /**
     * 处理配置文件中定义的 rewrites / redirects / headers
     */
    private static function handleProxyRules(Request $request): ?Response
    {
        $path = $request->path();

        // 1. Redirects 检查
        $redirects = Config::get('routing.redirects', []);
        foreach ($redirects as $source => $rule) {
            $dest = is_array($rule) ? $rule['destination'] : $rule;
            $permanent = is_array($rule) ? ($rule['permanent'] ?? false) : false;

            if ($source === $path) {
                return Response::redirect($dest, $permanent ? 301 : 302);
            }

            if (str_contains((string) $source, '*')) {
                $pattern = '#^' . str_replace('\*', '(.*)', preg_quote((string) $source, '#')) . '$#';
                if (preg_match($pattern, $path, $matches)) {
                    $replacement = $matches[1] ?? '';
                    $targetDest = str_replace('*', $replacement, (string) $dest);
                    return Response::redirect($targetDest, $permanent ? 301 : 302);
                }
            }
        }

        // 2. Rewrites 检查（重写请求 path）
        $rewrites = Config::get('routing.rewrites', []);
        foreach ($rewrites as $source => $dest) {
            if ($source === $path) {
                $_SERVER['REQUEST_URI'] = (string) $dest;
                break;
            }

            if (str_contains((string) $source, '*')) {
                $pattern = '#^' . str_replace('\*', '(.*)', preg_quote((string) $source, '#')) . '$#';
                if (preg_match($pattern, $path, $matches)) {
                    $replacement = $matches[1] ?? '';
                    $_SERVER['REQUEST_URI'] = str_replace('*', $replacement, (string) $dest);
                    break;
                }
            }
        }

        // 3. 全局 headers 规则
        $headersConfig = Config::get('routing.headers', []);
        foreach ($headersConfig as $source => $headers) {
            $pattern = '#^' . str_replace('*', '.*', $source) . '$#';
            if (preg_match($pattern, $path)) {
                foreach ($headers as $headerKey => $headerValue) {
                    header("{$headerKey}: {$headerValue}");
                }
            }
        }

        return null;
    }

    /**
     * 终止生命周期，冲刷输出缓冲区，唤醒 PHP-FPM 后台执行队列。
     */
    public static function terminate(): void
    {
        // 若处于 FPM 环境，立即向 Nginx/客户端冲刷所有 HTTP 报文
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        // 顺序执行后台副作用任务
        while (!empty(self::$afterCallbacks)) {
            $callback = array_shift(self::$afterCallbacks);
            try {
                $callback();
            } catch (Throwable $e) {
                Log::error('Air::after() callback failed: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }
    }

    /**
     * 清理状态（主要用于测试重置）。
     */
    public static function reset(): void
    {
        self::$afterCallbacks = [];
        self::$currentRequest = null;
        self::$middlewares = [];
    }
}
