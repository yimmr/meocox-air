# 请求与响应 (Request & Response)

Meocox Air 将底层 PHP 全局变量（`$_GET`, `$_POST`, `$_SERVER` 等）封存在不可变的 `Request` 对象中，杜绝全局状态污染，并提供强类型的读取手段。

---

## Request 对象

通过依赖注入或 `Air::request()` 获取当前请求：

### 1. 强类型参数获取

针对各种数据类型，避免手动做复杂的类型转换与判空：

```php
// 强类型转为 int，支持默认值
$page = $request->int('page', 1);

// 强类型转为 bool (智能识别 "true", "1", "false", 0)
$isActive = $request->bool('is_active', false);

// 浮点数获取
$amount = $request->float('amount', 0.0);

// 字符串获取
$keyword = $request->string('keyword');
```

### 2. JSON Payload 与动态参数读取

```php
// 检查请求头是否声明为 JSON
if ($request->isJson()) {
    $payload = $request->json();          // 获取全部 JSON 数组
    $userId = $request->json('user.id');  // 支持点语法深层获取
}

// 统一输入获取 (按优先级匹配: JSON -> POST -> 路由参数 -> GET)
$email = $request->input('email');

// 获取路由动态参数 (如 [id] 或 [...slug])
$id = $request->param('id');
```

### 3. 请求头与 Bearer 令牌

```php
// 自动解析 Authorization: Bearer <token>
$token = $request->bearerToken();

// 获取指定 Header
$userAgent = $request->header('User-Agent');

// 获取客户端真实 IP（自动兼容反向代理标头 X-Forwarded-For 与 X-Real-IP）
$clientIp = $request->ip();
```

---

## Response 对象

`Response` 提供丰富的链式与静态构造方法：

### 1. JSON 响应
```php
use Meocox\Http\Response;

return Response::json([
    'code' => 0,
    'data' => $user,
], 200, ['X-Custom-Header' => 'value']);
```

### 2. HTML 响应
```php
return Response::html('<h1>Hello World</h1>');
```

### 3. 重定向响应
```php
// 默认 302 临时重定向，传参 301 为永久重定向
return Response::redirect('/dashboard', 302);
```

### 4. 204 No Content
```php
return Response::noContent();
```

### 5. 流式响应 (Server-Sent Events 或大文件流)
```php
return Response::stream(function () {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');

    for ($i = 0; $i < 5; $i++) {
        echo "data: {\"count\": {$i}}\n\n";
        ob_flush();
        flush();
        sleep(1);
    }
});
```
