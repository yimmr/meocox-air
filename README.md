# Meocox Air

> 轻量、现代化且零外部依赖的 PHP 8.2+ 核心框架底座。

Meocox Air 是整个 Meocox 生态系统（包含应用、主题及拓展框架）的最底层基础包。以**被动库（Passive Library）**姿态设计，内核无任何宿主（如 WordPress）强绑定代码，具备 100% 跨宿主环境适配能力。

📖 **[阅读完整开发者文档与 API 参考手册 (docs/)](file:///home/imoncn/org/packages/meocox-air/docs/README.md)**

---

## 核心特性

- **100% 零外部依赖**：仅依赖 PHP 8.2+ 原生扩展（`pdo`, `mbstring`, `json`），无任何第三方包。
- **文件约定路由与 BFF**：
  - `page.php` 页面端点、`route.php` API 端点、`guard.php` 目录门禁、`_private/` 私有组件就近存放。
- **三级母版布局引擎**：
  - 优先级 1：`meocox.config.php` 配置强力覆写 / 禁用。
  - 优先级 2：同级/下级专有命名母版 `{segment}.layout.php`（如 `/login` 匹配 `login.layout.php`，消灭虚拟目录地狱）。
  - 优先级 3：默认级联嵌套 `layout.php`，子模板使用 `$this->slot()` 插槽。
- **Drizzle ORM RQB 风格关系查询**：
  - 声明式 `'with' => ['rewards' => true]`，内存批量装配，彻底杜绝 N+1 与笛卡尔积爆炸。
- **单一事实源（SSOT）与 JIT Schema 零开销编译**：
  - 新建模型只需声明 `schema(Table $t)`，自动生成建表 DDL 与出入库类型映射，首次执行 JIT 编译并由 OPcache 驻留。
  - 兼容老表（如 WordPress 现有表）直接声明 `$casts`。
- **财务级精度与类型防腐**：
  - `DECIMAL` 坚决保持 `string` 配合 `bcmath`，防浮点损耗。
  - 自动化 BackedEnum、JSON 与 WordPress 原生序列化串转换。
- **异步副作用任务 `Air::after()`**：
  - 基于 PHP-FPM `fastcgi_finish_request()`，先以微秒级时间向客户端输出响应，再于后台执行审计、埋点与消息推送。
- **嵌套事务支持**：
  - 基于 MySQL / SQLite Savepoints 实现真正的多层嵌套事务安全提交与回滚。

---

## 快速上手

### 1. 安装与自动加载

```json
{
    "require": {
        "php": ">=8.2"
    },
    "autoload": {
        "psr-4": {
            "Meocox\\": "packages/meocox-air/src/"
        }
    }
}
```

### 2. 配置文件 (`meocox.config.php`)

```php
use Meocox\Config;

return Config::define([
    'routing' => [
        'rewrites' => [
            '/api/v1/*' => '/api/v2/*',
        ],
        'layouts' => [
            '/screen/*' => false, // 禁用特定路由母版
        ],
    ],
    'database' => [
        'default' => 'mysql',
        'connections' => [
            'mysql' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'database' => 'meocox',
                'username' => 'root',
                'password' => '',
            ],
        ],
    ],
]);
```

### 3. 数据模型定义

```php
use Meocox\Database\Model;
use Meocox\Database\Table;

class User extends Model
{
    public static function schema(Table $t): void
    {
        $t->id();
        $t->string('name');
        $t->string('email')->unique();
        $t->decimal('balance', 14, 4)->default('0.0000');
        $t->json('settings');
        $t->timestamps();
    }

    protected static array $relations = [
        'rewards' => [
            'type' => 'hasMany',
            'model' => Reward::class,
            'foreignKey' => 'user_id',
        ],
    ];
}

// Drizzle RQB 风格查询
$users = User::findMany([
    'where' => ['status' => 'active'],
    'with' => ['rewards' => true],
    'limit' => 20,
]);
```

### 4. 路由与端点

- 页面渲染 (`app/routes/admin/page.php`):
```php
<h1>Welcome <?= $this->escape($this->request()->query('name', 'Admin')) ?></h1>
```

- API 端点 (`app/routes/api/users/route.php`):
```php
use Meocox\Http\Request;
use Meocox\Http\Response;

return [
    'GET' => function (Request $req): Response {
        return Response::json(['users' => User::all()]);
    },
];
```

---

## 运行自动化测试

```bash
php tests/run.php
```

All tests pass natively with zero external test runners required.
