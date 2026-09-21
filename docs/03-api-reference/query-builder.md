# QueryBuilder 查询构造器 API 参考

命名空间：`Meocox\Database\QueryBuilder`

`QueryBuilder` 提供链式流式构造与 Drizzle RQB 风格字面量配置双模查询能力。

---

## 链式构建方法

### `select(array|string $columns = ['*']): static`
指定要查询的字段列表。

### `where(string|Closure $column, mixed $operator = null, mixed $value = null): static`
添加 WHERE 条件，支持二元、三元及嵌套闭包语法：
```php
$qb->where('status', 'active');
$qb->where('age', '>=', 18);
$qb->where(fn($q) => $q->where('role', 'admin')->orWhere('vip', 1));
```

### `orWhere(string|Closure $column, mixed $operator = null, mixed $value = null): static`
添加 OR WHERE 条件。

### `whereIn(string $column, array $values, bool $not = false): static`
添加 IN / NOT IN 条件。

### `whereRaw(string $sql, array $bindings = []): static`
注入原生 WHERE 条件片段。

### `when(mixed $condition, callable $callback, ?callable $default = null): static`
条件构造。当 `$condition` 为真时执行 `$callback($qb, $condition)`。

### `orderBy(string $column, string $direction = 'ASC'): static`
添加排序规则（`'ASC'` 或 `'DESC'`）。

### `limit(int $limit): static` / `offset(int $offset): static`
设置限制条数与起始偏移量。

### `with(array|string $relations): static`
声明需要批量预装配的关联关系名（Drizzle RQB 风格）。

---

## Drizzle RQB 字面量配置

### `applyParams(array $params): static`
解析并应用字面量参数数组：
```php
$qb->applyParams([
    'columns' => ['id', 'username', 'balance'],
    'where' => [
        'status' => 'active',
        'balance' => ['gte' => '50.0000', 'lte' => '1000.0000'],
        'role' => ['admin', 'manager'], // 自动解析为 IN (admin, manager)
    ],
    'orderBy' => ['created_at' => 'desc'],
    'limit' => 20,
    'with' => ['rewards' => true],
]);
```

---

## 终结执行方法

### `get(): array`
执行查询并返回多条结果数组（若声明了 `with` 则自动批量完成无损装配）。

### `first(): ?array`
获取满足条件的单条记录（若不存在返回 `null`）。

### `count(string $columns = '*'): int`
统计符合条件的记录总数。

### `exists(): bool`
判断是否存在符合条件的记录。

### `paginate(int $page = 1, int $perPage = 15): Paginator`
执行标准 Offset 分页，返回包含 `data` 与分页元数据的 `Paginator` 对象。

### `insert(array $values): int|string`
插入一条记录并返回自增主键 ID。

### `update(array $values): int`
批量更新记录并返回受影响行数。

### `delete(): int`
删除匹配条件的记录并返回受影响行数。
