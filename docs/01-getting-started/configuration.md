# 项目配置 (meocox.config.php)

Meocox Air 借鉴 Next.js 的配置范式，使用项目根目录下的 `meocox.config.php` 作为全局配置中心，并提供 `Config::define([...])` 工具函数确保在 PhpStorm、VS Code 等 IDE 中获得 **100% 自动补全与类型检查**。

---

## 配置文件结构

在项目根目录下创建 `meocox.config.php`：

```php
<?php

declare(strict_types=1);

use Meocox\Config;

return Config::define([
    // 基础路径：支持子目录部署（完美适配 WordPress 插件或主题嵌入路径）
    'basePath' => '',

    // 路由高级控制（类 Next.js 反向代理能力）
    'routing' => [
        // 内部路径重写（客户端 URL 保持不变，内核转发）
        'rewrites' => [
            '/api/v1/*' => '/api/v2/*',
        ],

        // 外部重定向（HTTP 301 / 302）
        'redirects' => [
            '/old-login' => [
                'destination' => '/auth/login',
                'permanent' => true,
            ],
        ],

        // 全局响应头注入
        'headers' => [
            '/*' => [
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
            ],
            '/api/*' => [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            ],
        ],

        // 母版优先级 1：路由级母版覆写规则
        'layouts' => [
            '/screen/*' => false,  // 大屏展示页面禁用所有外层母版
            '/embed/*' => false,   // 第三方嵌入页禁用母版
        ],
    ],

    // 数据库连接池与慢查询监听阈值
    'database' => [
        'default' => 'mysql',
        'slowQueryThreshold' => 300, // 超过 300ms 自动触发告警日志
        'connections' => [
            'mysql' => [
                'driver' => 'mysql',
                'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
                'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
                'database' => $_ENV['DB_DATABASE'] ?? 'meocox',
                'username' => $_ENV['DB_USERNAME'] ?? 'root',
                'password' => $_ENV['DB_PASSWORD'] ?? '',
                'charset' => 'utf8mb4',
            ],
            'sqlite' => [
                'driver' => 'sqlite',
                'database' => __DIR__ . '/runtime/database.sqlite',
            ],
        ],
    ],

    // 路径映射
    'paths' => [
        'runtime' => __DIR__ . '/runtime',
        'routes' => __DIR__ . '/app/routes',
    ],

    // 全局门面类别名（支持自定义与多别名映射，在视图和模板中免 use 声明直接调用）
    'aliases' => [
        'MeocoxAir' => \Meocox\Air::class, // 支持为同一门面定义多个别名
    ],

    // 调试模式
    'debug' => (bool) ($_ENV['APP_DEBUG'] ?? false),
]);
```

---

## 全局门面别名 (Aliases)

在传统 PHP 视图模板（如 `page.php`、`layout.php`）中，混合书写 HTML 与完整命名空间（如 `\Meocox\Air::VERSION`、`\Meocox\Auth::check()`）会导致代码冗长。如果使用 `use` 声明，每个视图文件又需重复添加。

Meocox Air 基于 PHP 原生 `class_alias` 机制提供了全局别名自动注册。`class_alias` 是 Zend Engine 底层符号表指针级映射，**零运行时查找损耗、零内存额外开销**。

### 默认内置别名
框架在生命周期启动时默认注册以下别名：
- `Air` $\rightarrow$ `\Meocox\Air::class`
- `Auth` $\rightarrow$ `\Meocox\Auth::class`
- `DB` $\rightarrow$ `\Meocox\DB::class`
- `Config` $\rightarrow$ `\Meocox\Config::class`

### 自定义与多别名映射
你可以在 `meocox.config.php` 的 `'aliases'` 数组中追加自定义别名，甚至为同一底层类映射多个别名：

```php
'aliases' => [
    'MeocoxAir' => \Meocox\Air::class,
    'AppDB'     => \Meocox\DB::class,
    // 禁用某个默认别名
    'Config'    => false,
],
```

若希望完全关闭框架的所有别名注册，直接设置 `'aliases' => false` 即可。

### IDE 智能补全与 Helper 生成
为了避免 PhpStorm、VS Code (Intelephense) 等静态分析工具报告 `Undefined class`，可以使用 `Air::generateIdeHelper()` 一键生成项目级 IDE 补全辅助文件（类似 Laravel 的 `_ide_helper.php`）：

```php
// 生成到项目根目录（该文件仅供 IDE 索引分析，运行时不加载）
\Meocox\Air::generateIdeHelper(__DIR__ . '/_ide_helper.php');
```

---

## 环境变量 (.env)

Air 内置零依赖的 `Dotenv` 解析器，支持常规语法及变量引用展开：

```env
APP_NAME="Meocox Application"
APP_DEBUG=true
DB_HOST=127.0.0.1
DB_DATABASE=my_app
LOG_CHANNEL="app"
GREETING="Hello from ${APP_NAME}"
```

在程序入口处加载：

```php
use Meocox\Config\Dotenv;

Dotenv::load(__DIR__ . '/.env');
```

---

## 静态读取配置

配置在系统启动后以只读仓库管理，支持点语法访问深层键：

```php
use Meocox\Config;

$dbHost = Config::get('database.connections.mysql.host', '127.0.0.1');
$isDebug = Config::get('debug', false);
```
