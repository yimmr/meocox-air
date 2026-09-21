# 声明式解耦鉴权 (Data Access Layer)

很多框架（如 Laravel 或 WordPress）将鉴权逻辑强行与特定的 Cookie、Session 驱动或全局函数（如 `wp_get_current_user()`）深度绑定，导致代码很难迁移或在非传统 Web 场景下复用。

Meocox Air 借鉴 Next.js **Data Access Layer (DAL)** 模式，保持底层 100% 宿主无关。

---

## 宿主注册解析器 (`Auth::resolver`)

在应用程序启动阶段，宿主环境向 `Auth` 门面注册一个解析器闭包：

### 示例 1：在 WordPress 宿主环境中注册
```php
use Meocox\Auth;

Auth::resolver(function ($request) {
    if (!function_exists('wp_get_current_user')) {
        return null;
    }

    $wpUser = wp_get_current_user();
    if ($wpUser && $wpUser->ID > 0) {
        return [
            'id' => $wpUser->ID,
            'username' => $wpUser->user_login,
            'roles' => (array) $wpUser->roles,
        ];
    }

    return null;
});
```

### 示例 2：基于 JWT Bearer Token 注册
```php
use Meocox\Auth;

Auth::resolver(function ($request) {
    $token = $request->bearerToken();
    if (!$token) {
        return null;
    }

    return JwtService::verify($token);
});
```

---

## 业务层调用

业务代码（无论是 `guard.php`、`page.php` 还是服务类）统一使用强类型的静态 API：

```php
use Meocox\Auth;

// 1. 判断是否登录
if (Auth::check()) {
    // 已登录
}

// 2. 判断是否为访客
if (Auth::guest()) {
    // 未登录
}

// 3. 获取当前登录用户 ID (标量)
$userId = Auth::id();

// 4. 获取用户完整实体/数组
$user = Auth::user();
```

通过这种架构，无论底层认证方式是从 Session 改为 JWT，还是从独立应用迁移到 WordPress 插件，业务核心代码一行都不需要改动。
