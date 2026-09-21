# 目录门禁守卫 (guard.php)

在复杂的 Web 应用中，权限拦截不应该散落在每个控制器的开头，也不应该在根路由堆叠面条式的全局拦截规则。

Meocox Air 提供了基于目录的 **`guard.php` 门禁机制**。

---

## 门禁工作原理

当请求匹配到某个具体路由时，路由器会自根目录向下逐层检查每级目录中的 `guard.php`：

1. 如果 `guard.php` 返回一个 `Response` 对象（如重定向到登录页或返回 HTTP 401/403），**立即短路中断请求**，后续目录门禁和页面控制器将不再执行。
2. 如果返回 `null` 或 `true`，则视为放行，继续向下穿透。

```mermaid
flowchart TD
    Req[进入请求: /admin/finance/reports] --> G1{app/routes/guard.php}
    G1 -- 放行 (null) --> G2{admin/guard.php}
    G2 -- 检查是否是管理员? -->|否| R1[返回 403 Forbidden]
    G2 -- 是 (放行) --> G3{finance/guard.php}
    G3 -- 检查财务模块权限? -->|通过| Controller[执行 page.php]
```

---

## 编写门禁守卫

在需要受保护的目录下放置 `guard.php`，导出一个可调用闭包：

```php
<!-- app/routes/admin/guard.php -->
<?php

use Meocox\Auth;
use Meocox\Http\Request;
use Meocox\Http\Response;

return function (Request $request): ?Response {
    // 1. 检查是否登录
    if (Auth::guest($request)) {
        return Response::redirect('/auth/login?redirect=' . urlencode($request->uri()));
    }

    // 2. 检查权限角色
    $user = Auth::user($request);
    if (!in_array('admin', $user['roles'] ?? [], true)) {
        return Response::json(['error' => 'Forbidden: Admins only'], 403);
    }

    // 3. 放行
    return null;
};
```

---

## 优势对比

- **相比于传统全局路由匹配**：不需要在路由表里硬编码匹配正则（如 `/admin/*`），目录重命名或迁移时规则自动跟随，杜绝漏配安全漏洞。
- **相比于中间件堆叠**：门禁与页面紧密就近存放（Colocation），职责一目了然。
