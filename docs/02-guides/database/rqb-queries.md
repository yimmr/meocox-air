# Drizzle RQB 风格关联查询 (Relational Query Builder)

传统 ORM 在处理关联数据时面临两大陷阱：
1. **Lazy Loading 导致的 N+1 查询地狱**：循环访问 `$user->rewards` 时，发起数百次数据库查询。
2. **Eager Loading 滥用 JOIN 产生的笛卡尔积爆炸**：多表联查时，主表字段在网络传输中被重复复制数万次，内存直接溢出，且存在不同表同名 `id` 字段相互覆盖的致命风险。

Meocox Air 深度吸收 **Drizzle ORM RQB** 的精髓，采用**声明式字面量 + 内存树形装配**。

---

## 声明关系模型

在模型中通过静态属性 `$relations` 声明关联关系：

```php
namespace App\Database;

use Meocox\Database\Model;

class User extends Model
{
    protected static array $relations = [
        'rewards' => [
            'type' => 'hasMany',               // 关联类型: hasMany, hasOne, belongsTo
            'model' => Reward::class,          // 目标关联模型
            'foreignKey' => 'user_id',         // 外键字段
            'localKey' => 'id',                // 本地主键字段 (可选，默认 id)
        ],
        'profile' => [
            'type' => 'hasOne',
            'model' => Profile::class,
            'foreignKey' => 'user_id',
        ],
    ];
}
```

---

## 批量无损查询 (`with`)

使用 `findMany` 或链式构造器，只需声明 `'with' => ['rewards' => true]`：

```php
// 查询活跃用户及其领奖记录
$users = User::findMany([
    'where' => [
        'is_active' => true,
        'balance' => ['gte' => '100.0000'], // 大于等于 100
    ],
    'with' => [
        'rewards' => true,
        'profile' => true,
    ],
    'limit' => 20,
]);
```

### 底层实际执行的 SQL：
1. 第一条 SQL（查主表）：
   ```sql
   SELECT * FROM `users` WHERE `is_active` = 1 AND `balance` >= '100.0000' LIMIT 20;
   ```
2. 第二条 SQL（批量查 rewards）：
   ```sql
   SELECT * FROM `rewards` WHERE `user_id` IN (1, 2, 5, 8, ...);
   ```
3. 第三条 SQL（批量查 profiles）：
   ```sql
   SELECT * FROM `profiles` WHERE `user_id` IN (1, 2, 5, 8, ...);
   ```

**内存拼装结果**：
Air 在内存中建立哈希字典，将 `rewards` 和 `profile` 自动装配进各自的父级用户数据中，组装为树形结构：

```json
[
  {
    "id": 1,
    "username": "alice",
    "rewards": [
      { "id": 101, "user_id": 1, "points": 50 },
      { "id": 102, "user_id": 1, "points": 100 }
    ],
    "profile": { "user_id": 1, "bio": "Developer" }
  }
]
```

### 为什么这比 JOIN 更好？
- **零笛卡尔积**：即使一个用户有 100 条奖励，也不会导致用户基础信息在网络上传输 100 次。
- **零列名冲突**：`users.id` 和 `rewards.id` 各自处于独立的数据字典中，永远不会发生 ID 混淆覆盖。
- **性能更优**：两条极度优化的小查询（命中索引）在现代数据库中远快于复杂的多表 JOIN 计算。
