# 单一事实源与 JIT 架构 (Schema & JIT)

传统 ORM（如 Eloquent、Doctrine）最大的痛点是**元数据重复与运行时开销**：
1. 建表写一次迁移文件，实体里再写一遍 `$casts` / `$fillable` / Annotations，任何修改都要改多处。
2. 每次请求都要通过反射动态解析类，分配大量中间对象。

Meocox Air 借鉴 **Drizzle ORM** 践行**单一事实源（Single Source of Truth, SSOT）**与 **JIT 编译缓存**。

---

## 单一事实源定义表结构

在自建新表中，你**只需要声明 `schema(Table $t)`**，禁止重复声明 `$casts`：

```php
<?php

declare(strict_types=1);

namespace App\Database;

use Meocox\Database\Model;
use Meocox\Database\Table;

class User extends Model
{
    /**
     * 单一事实源：Schema 蓝图同时决定了建表 DDL 与运行时类型转换
     */
    public static function schema(Table $t): void
    {
        $t->id();                                    // BIGINT 自增主键
        $t->string('username', 64)->unique();        // 唯一索引字符串
        $t->string('email')->unique();
        $t->string('password_hash');
        $t->decimal('balance', 14, 4)->default('0.0000'); // 财务金额，强制保持 string
        $t->boolean('is_active')->default(1);        // 布尔，出入库自动转换
        $t->json('preferences');                     // JSON 自动双向编解码
        $t->timestamps();                            // created_at & updated_at
    }
}
```

> [!TIP]
> **老表（如对接 WordPress 原生表）如何处理？**
> 如果表已经在数据库中存在（如 `wp_posts`, `wp_users`），模型中**只写 `$casts`，不写 `schema()`** 即可：
> ```php
> class WpUser extends Model
> {
>     protected static ?string $table = 'wp_users';
>     protected static array $casts = [
>         'user_status' => 'int',
>     ];
> }
> ```

---

## JIT 零运行时开销机制

`schema(Table $t)` 不需要也不应该在每次 HTTP 请求中重复执行。Air 实现了 **JIT（Just-In-Time）模式编译器**：

1. **首次触发**：当模型首次执行查询或写入时，`SchemaManager` 执行 `schema()` 提取字段元数据、类型转换映射（Casts）与白名单。
2. **编译原子写入**：将元数据生成为原生的纯 PHP 数组脚本（如 `runtime/schema/App_Database_User.php`），并通过临时文件重命名保证**并发原子写入**。
3. **OPcache 常驻**：脚本被载入 PHP 引擎后，由 OPcache 直接常驻在操作系统的共享内存中。后续请求读取该元数据的耗时为 **0 纳秒**，对象分配为 **0**。
4. **开发环境热重载**：在 `debug = true` 时，系统自动对比模型源码文件的 `filemtime`，修改模型文件后立即自动重新编译，无需手动清理缓存。

---

## 财务级高精度 (DECIMAL)

浮点数计算（`0.1 + 0.2 !== 0.3`）在电商与金融业务中是灾难性的。

Air 的 `Hydrator` 对待 `decimal` 字段坚决**不出库为 PHP float**，始终保持为高精度 `string`，供开发者使用 `bcmath` 进行无损运算：

```php
// 出库后 $user->balance 保持严格的字符串 '199.9900'
$newBalance = bcadd($user->balance, '0.0100', 4);
```
