# DB 数据库门面 API 参考

命名空间：`Meocox\DB`

`DB` 提供统一的数据库连接池访问、流畅查询构造器、事务及全局监听门面。

---

## 方法签名一览

### `DB::connection(?string $name = null): Connection`
获取底层指定名称的 `Connection` 连接实例（缺省时使用 `database.default` 配置）。
- **返回**：`Meocox\Database\Connection`

---

### `DB::table(string $table, ?string $connection = null): QueryBuilder`
创建针对指定数据表的查询构造器。
- **示例**：
  ```php
  $user = DB::table('users')->where('id', 1)->first();
  ```

---

### `DB::transaction(callable $callback, ?string $connection = null): mixed`
开启一个安全的数据库事务闭包（自动支持 Savepoint 嵌套）。
- **参数**：
  - `$callback`: `callable(Connection $conn): mixed`
- **行为**：
  - 闭包执行成功自动 COMMIT（或 RELEASE SAVEPOINT）。
  - 抛出任何异常自动 ROLLBACK（或 ROLLBACK TO SAVEPOINT）并重新抛出。
- **返回**：闭包内的返回值。

---

### `DB::query(string $sql, array $bindings = [], ?string $connection = null): array`
执行原生 SELECT 预处理查询。
- **示例**：
  ```php
  $rows = DB::query('SELECT * FROM users WHERE status = ?', ['active']);
  ```

---

### `DB::execute(string $sql, array $bindings = [], ?string $connection = null): int`
执行原生 INSERT / UPDATE / DELETE / DDL 预处理语句。
- **返回**：受影响的记录行数。

---

### `DB::listen(callable $listener): void`
注册全局 SQL 监听器。
- **参数**：
  - `$listener`: `callable(string $sql, array $bindings, float $durationMs): void`
