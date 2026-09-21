# Response 响应对象 API 参考

命名空间：`Meocox\Http\Response`

`Response` 负责管理 HTTP 响应状态码、响应头与响应载荷（包含流式闭包）。

---

## 快速工厂方法

### `Response::json(mixed $data, int $status = 200, array $headers = []): static`
构建 JSON 响应。自动设置 `Content-Type: application/json; charset=utf-8`，采用 `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES` 编码。

### `Response::html(string $html, int $status = 200, array $headers = []): static`
构建 HTML 响应。自动设置 `Content-Type: text/html; charset=utf-8`。

### `Response::text(string $text, int $status = 200, array $headers = []): static`
构建纯文本响应。

### `Response::redirect(string $url, int $status = 302, array $headers = []): static`
构建 HTTP 重定向响应，自动注入 `Location: $url`。

### `Response::noContent(array $headers = []): static`
构建 204 No Content 响应。

### `Response::stream(callable $callback, int $status = 200, array $headers = []): static`
构建流式输出响应。传入闭包在 `send()` 时执行，常用于 SSE 或大文件输出。

---

## 实例方法

### `setStatusCode(int $code): static`
设置 HTTP 状态码。

### `getStatusCode(): int`
获取 HTTP 状态码。

### `setHeader(string $name, string $value): static`
设置或覆盖指定的响应头。

### `getHeaders(): array`
获取全部响应头。

### `setContent(string $content): static`
设置响应体内容。

### `getContent(): string`
获取响应体内容。

### `send(): void`
将状态码、HTTP 标头及响应体冲刷输出到客户端。
