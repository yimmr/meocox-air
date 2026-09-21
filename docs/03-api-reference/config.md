# Config 配置门面 API 参考

命名空间：`Meocox\Config`

`Config` 门面管理应用全局只读配置，支持多维数组点语法访问，并提供 IDE 提示辅助函数。

---

## 方法签名一览

### `Config::define(array $config): array`
辅助定义配置数组。在 `meocox.config.php` 中使用，可获得完备的 PHPStan / IDE 智能提示与静态类型约束。
- **参数**：
  - `$config`: 符合 Meocox 配置契约的配置项数组。
- **返回**：原样返回传入的 `$config`。

---

### `Config::get(string $key, mixed $default = null): mixed`
获取配置项值，支持点语法读取深层嵌套键。
- **示例**：
  ```php
  $host = Config::get('database.connections.mysql.host', '127.0.0.1');
  ```

---

### `Config::set(string $key, mixed $value): void`
动态修改或注入配置项。
- **示例**：
  ```php
  Config::set('app.env', 'testing');
  ```

---

### `Config::has(string $key): bool`
判断指定配置项是否存在。
- **示例**：
  ```php
  if (Config::has('database.connections.sqlite')) { ... }
  ```

---

### `Config::load(string|array $config): void`
合并载入配置文件（文件路径）或纯数组。
- **示例**：
  ```php
  Config::load(__DIR__ . '/meocox.config.php');
  ```
