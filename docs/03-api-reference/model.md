# Model 数据模型基类 API 参考

命名空间：`Meocox\Database\Model`

`Model` 是所有业务数据实体的抽象基类，基于单一事实源（SSOT）设计，杜绝臃肿的 ActiveRecord 封装。

---

## 声明属性与钩子

### `public static function schema(Table $t): void`
**单一事实源核心**。声明新自建表的结构、类型与索引（供 JIT 编译）。

### `protected static ?string $table`
自定义数据表名（缺省时根据类名蛇形复数自动推导）。

### `protected static ?string $primaryKey`
自定义主键字段名（缺省为 `'id'`）。

### `protected static array $casts`
类型映射字典（仅对接已有老表/WordPress 表时需声明）。

### `protected static array $relations`
模型关联字典配置：
```php
protected static array $relations = [
    'rewards' => [
        'type' => 'hasMany',      // 'hasMany' | 'hasOne' | 'belongsTo'
        'model' => Reward::class,
        'foreignKey' => 'user_id',
        'localKey' => 'id',
    ],
];
```

---

## 静态查询方法

### `Model::find(mixed $id): ?static`
根据主键查找记录。

### `Model::findOrFail(mixed $id): static`
根据主键查找记录，不存在时抛出 `NotFoundException`（自动映射 HTTP 404）。

### `Model::findMany(array $params = []): list<static>`
使用 Drizzle RQB 风格字面量批量查询模型对象集合。

### `Model::create(array $attributes): static`
清洗数据（过滤非白名单字段，转换 JSON/布尔/枚举）并插入持久化到数据库。

### `Model::query(): QueryBuilder`
获取绑定了当前模型及其类型转换上下文的 `QueryBuilder` 实例。

---

## 实例操作方法

### `update(array $attributes): bool`
更新实例字段并保存。

### `save(): bool`
持久化当前实例的脏字段（Dirty Attributes）。

### `delete(): bool`
从数据库中删除当前记录。

### `toArray(): array` / `toJson(): string`
将模型数据转为纯数组或 JSON 字符串。
