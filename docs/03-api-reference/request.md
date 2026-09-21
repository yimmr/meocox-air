# Request 请求对象 API 参考

命名空间：`Meocox\Http\Request`

`Request` 封装所有 HTTP 输入数据，提供不可变强类型转换与安全隔离。

---

## 实例化

```php
// 从原生 PHP 超全局数组快速构建
$request = Request::createFromGlobals();
```

---

## 请求信息获取

### `method(): string`
获取大写的 HTTP 请求方法（支持 `X-HTTP-Method-Override` 及 `_method` 覆写）。
- **返回**：`'GET' | 'POST' | 'PUT' | 'DELETE' | 'PATCH' | ...`

### `uri(): string`
获取完整的请求 URI（包含 Query 字符串）。

### `path(): string`
获取规范化后的绝对路径（不包含 Query 参数，格式为以 `/` 开头）。

### `ip(): string`
获取客户端真实 IP（优先按顺序解析 `X-Forwarded-For`、`X-Real-IP`、`REMOTE_ADDR`）。

### `header(string $key, ?string $default = null): ?string`
获取指定 HTTP 请求头（键名大小写不敏感，支持连字符与下划线）。

### `headers(): array`
获取所有请求头组成的键值对字典。

### `bearerToken(): ?string`
解析 `Authorization: Bearer <token>` 请求头。若未携带或格式不符返回 `null`。

---

## 输入读取与强类型转换

### `int(string $key, int $default = 0): int`
读取整型输入。

### `float(string $key, float $default = 0.0): float`
读取浮点型输入。

### `bool(string $key, bool $default = false): bool`
读取布尔型输入。智能将 `'true'`, `'1'`, `1`, `true` 识别为 `true`。

### `string(string $key, string $default = ''): string`
读取字符串标量输入。

### `input(string $key, mixed $default = null): mixed`
统一按优先级获取输入参数：`JSON -> POST -> 路由参数 -> GET`。

### `all(): array`
获取合并后的完整输入数据集。

### `only(array $keys): array`
仅提取指定键的数据字典。

### `except(array $keys): array`
排除指定键后的数据字典。

### `param(string $key, ?string $default = null): ?string`
获取文件路由中动态匹配的参数（如 `[id]` 或 `[...slug]`）。

### `isJson(): bool`
检查请求的 `Content-Type` 是否声明为 JSON。

### `isAjax(): bool`
检查是否为 AJAX 请求（`X-Requested-With: XMLHttpRequest`）。
