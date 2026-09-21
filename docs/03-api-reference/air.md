# Air 核心门面 API 参考

命名空间：`Meocox\Air`

`Air` 是 Meocox Air 框架的核心全局入口，负责生命周期编排、全局中间件注册与后置异步任务调度。

---

## 方法签名一览

### `Air::version(): string`
获取当前 Meocox Air 框架的版本号。
- **返回**：`string`（如 `'0.1.0'`）

---

### `Air::request(): Request`
获取当前请求的单例 `Request` 对象。如果尚未初始化，将自动从全局超全局变量（`$_GET`, `$_POST` 等）创建。
- **返回**：`Meocox\Http\Request`

---

### `Air::use(mixed $middleware): void`
向全局洋葱中间件管道中注册一个中间件。
- **参数**：
  - `$middleware`: `MiddlewareInterface` 实例、中间件类名（字符串），或符合签名的 `callable(Request, callable): Response`。

---

### `Air::after(callable $callback): void`
注册在 HTTP 响应输出给客户端后执行的后置副作用任务。
- **参数**：
  - `$callback`: `callable(): void` 后台执行闭包。
- **注意**：
  - 在 FPM 环境下，系统先通过 `fastcgi_finish_request()` 将报文完全冲刷到客户端断开连接，然后再顺序执行已注册的闭包。
  - 闭包中的未捕获异常会被自动记录在日志中，不会影响客户端已接收的正常响应。

### `Air::registerAliases(): void`
根据 `meocox.config.php` 中的配置与内置核心门面映射，自动调用 PHP 原生 `class_alias` 注册全局别名。
- **默认映射**：
  - `Air` $\rightarrow$ `\Meocox\Air::class`
  - `Auth` $\rightarrow$ `\Meocox\Auth::class`
  - `DB` $\rightarrow$ `\Meocox\DB::class`
  - `Config` $\rightarrow$ `\Meocox\Config::class`
- **说明**：在 `Air::run()` 启动时会自动被隐式调用；若独立运行或编写脚本，也可按需显式调用。

---

### `Air::generateIdeHelper(?string $outputPath = null): string`
为 PhpStorm、VS Code (Intelephense) 等 IDE 静态分析工具生成门面类别名补全代码（纯声明，不包含业务实现）。
- **参数**：
  - `$outputPath`: 可选，生成文件的物理保存绝对路径（如 `__DIR__ . '/_ide_helper.php'`）。
- **返回**：`string`（生成的 PHP 脚本字符串内容）。

---

### `Air::run(?Request $request = null, ?string $routesPath = null): void`
启动并执行完整的 HTTP 应用程序生命周期。
- **流程**：
  1. 自动调用 `Air::registerAliases()` 注入全局门面别名。
  2. 读取并应用 `meocox.config.php` 中的 rewrites、redirects 与全局 headers。
  3. 穿透全局中间件管道。
  4. 执行文件约定路由器（含 `guard.php` 门禁与母版多层渲染）。
  5. 捕捉异常并格式化为标准 JSON 或 HTML 响应报文输出。
  6. 调用 `Air::terminate()` 结束前台输出并执行 `after` 队列。

---

### `Air::terminate(): void`
冲刷输出流并唤醒后置任务执行队列。
- **注意**：如果自定义调用了生命周期而不是直接使用 `Air::run()`，应在输出完成后显式调用 `Air::terminate()`。
