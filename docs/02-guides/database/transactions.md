# 嵌套事务与慢查询监控 (Transactions & Monitoring)

在分布式或模块化业务开发中，一个服务往往会在自身开启事务的同时，调用另一个同样使用了事务的方法。原生 PDO 不支持嵌套 `beginTransaction()`，重复调用会直接抛出致命异常。

Meocox Air 内置基于 **Savepoints 保存点** 的事务管理器，支持无限层级的安全嵌套。

---

## 闭包式自动事务 (`DB::transaction`)

使用闭包包裹业务逻辑，无需手动写繁琐的 `try...catch`、`commit` 和 `rollback`：

```php
use Meocox\DB;

$order = DB::transaction(function () use ($userId, $items) {
    // 1. 扣减库存
    DB::table('products')->where('id', 1)->decrement('stock', 2);

    // 2. 创建订单记录
    $orderId = DB::table('orders')->insert([
        'user_id' => $userId,
        'status' => 'pending',
    ]);

    // 闭包正常结束 -> 自动 COMMIT
    return $orderId;
});
// 若闭包中抛出任何 Throwable 异常 -> 自动 ROLLBACK，并重新抛出异常供上层处理
```

---

## 嵌套事务与 Savepoints 机制

当外层事务调用包含内层事务的服务方法时：

```php
DB::transaction(function () {
    // 开启主事务: PDO::beginTransaction()

    OrderService::create();

    DB::transaction(function () {
        // 内层事务: 自动降级为 SAVEPOINT trans_1
        PointService::deduct();
        // 内层提交: 自动执行 RELEASE SAVEPOINT trans_1
    });

    // 主事务提交: PDO::commit()
});
```

如果内层发生异常，可精准回滚到特定 Savepoint，保证事务的 ACID 完整性。

---

## 慢查询监听与预警

在 `meocox.config.php` 中配置全局慢查询阈值：

```php
'database' => [
    'slowQueryThreshold' => 300, // 毫秒 (默认 500ms)
],
```

Air 执行每条 SQL 时都会记录微秒级执行耗时：
1. **自动记入日志**：超过阈值的 SQL 会被自动记录为 `WARNING` 级别日志（包含执行耗时、SQL 原文与参数绑定）。
2. **自定义监听器**：你可以注册全局事件钩子，接入 Prometheus、Sentry 或自定义性能分析工具：

```php
use Meocox\DB;

DB::listen(function (string $sql, array $bindings, float $durationMs) {
    if ($durationMs > 200) {
        // 上报给 APM 或监控系统
        Metrics::timing('sql.query_time', $durationMs);
    }
});
```
