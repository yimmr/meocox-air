# 洋葱模型中间件 (Middleware)

Meocox Air 内置经典的**洋葱模型（Onion Architecture）**中间件管道。请求进入时自外向内穿透各层，响应生成后自内向外反向穿出。

---

## 编写中间件

实现 `Meocox\Http\MiddlewareInterface` 契约，或直接使用可调用的闭包：

```php
<?php

declare(strict_types=1);

namespace App\Middlewares;

use Meocox\Http\MiddlewareInterface;
use Meocox\Http\Request;
use Meocox\Http\Response;

class ExecutionTimerMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $startTime = microtime(true);

        // 1. 将请求传递给洋葱下一层
        $response = $next($request);

        // 2. 捕获响应并注入自定义性能计时标头
        $duration = (microtime(true) - $startTime) * 1000;
        $response->setHeader('X-Response-Time', sprintf('%.2fms', $duration));

        return $response;
    }
}
```

---

## 注册全局中间件

在应用启动入口使用 `Air::use(...)`：

```php
use App\Middlewares\ExecutionTimerMiddleware;
use Meocox\Air;

Air::use(new ExecutionTimerMiddleware());

// 也可以直接注册匿名闭包中间件
Air::use(function ($request, $next) {
    // 前置逻辑
    $response = $next($request);
    // 后置逻辑
    return $response;
});
```
