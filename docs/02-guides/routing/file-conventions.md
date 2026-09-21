# 文件约定路由 (File Conventions)

Meocox Air 采用约定优于配置的文件系统路由机制。你的目录结构直接映射为 Web 应用程序的 URL 路径。

---

## 约定文件一览

在路由根目录（现代标准推荐直接使用 `app/`）下，特殊文件名具有确定的职责：

| 特殊文件名 | 角色说明 |
| :--- | :--- |
| **`page.php`** | 页面渲染端点（用于呈现 HTML 视图，配合母版布局） |
| **`route.php`** | BFF / API 端点（返回 JSON、数据流或自定义响应） |
| **`layout.php`** | 当前层级与子层级的公共母版布局（通过 `$this->slot()` 插槽渲染） |
| **`{name}.layout.php`** | **专有命名母版**（例如 `login.layout.php`，用于同级或下级特定页面的母版替换） |
| **`guard.php`** | 当前目录及子目录的**门禁守卫**（用于鉴权、RBAC 检查或重定向） |
| **`loading.php`** | **页面级整页骨架屏**（首屏瞬出骨架，随后微脚本携带 `X-MeocoxAir-Target: page` 异步就地替换） |
| **`error.php`** | **母版级局部错误边界**（捕获子层级异常并保留外层母版导航栏友好降级，返回 HTTP 500） |
| **`not-found.php`** | 局部或全局 404 缺省视图 |

---

## 路由目录映射与架构解耦示例

Meocox Air 推荐将 **路由与视图呈现** 放在 `app/`，将 **业务领域代码（Model, Service, Widget）** 放在 `src/`（遵循 Composer PSR-4 规范）：

```text
my-project/
├── app/                       # 【应用路由树】
│   ├── layout.php             # 全局根母版（Header, Footer 等）
│   ├── page.php               # 映射根路径: /
│   ├── error.php              # 全局错误兜底（保留母版）
│   │
│   ├── auth/                  # 映射路径前缀: /auth
│   │   ├── layout.php         # 默认 Auth 母版
│   │   ├── login.layout.php   # 专为 login 定制的专有命名母版
│   │   └── login/
│   │       └── page.php       # 映射: /auth/login (使用 login.layout.php)
│   │
│   ├── dashboard/
│   │   ├── guard.php          # 门禁：未登录者直接重定向到 /auth/login
│   │   ├── layout.php         # 控制台侧边栏母版
│   │   └── page.php           # 映射: /dashboard（可使用 $this->suspense 挂起局部部件）
│   │
│   ├── analytics/
│   │   ├── loading.php        # 页面级整页骨架屏（首屏秒开）
│   │   └── page.php           # 映射: /analytics
│   │
│   ├── users/
│   │   ├── [id]/              # 动态单段参数 (/users/123)
│   │   │   └── page.php
│   │   └── [...slug]/         # 捕获所有多段参数 (/users/a/b/c)
│   │       └── page.php
│   │
│   └── api/
│       └── users/
│           └── route.php      # API 端点: /api/users
│
├── src/                       # 【业务领域代码 (PSR-4: App\*)】
│   ├── Database/              # 模型实体 (User.php, Note.php)
│   └── widgets/               # 独立部件 (含 skeleton.php 与 view.php)
│       └── StatsCard/
│           ├── skeleton.php   # 骨架屏占位
│           └── view.php       # 真实数据视图
│
└── meocox.config.php          # 'paths.routes' 默认自动解析为 __DIR__ . '/app'
```

---

## 局部区域骨架屏：`$this->suspense(component: ..., fallback: ...)`

对于页面内耗时较长的卡片或图表，无需整页等待。通过 `$this->suspense()` 声明挂起：

```php
<!-- app/dashboard/page.php -->

<h1>控制台</h1>

<!-- 首屏瞬间渲染 skeleton.php，随后微脚本携带 X-MeocoxAir-Suspense 异步并发拉取真实 view.php -->
<?= $this->suspense(
    component: 'StatsCard',
    fallback: 'widgets/StatsCard/skeleton.php',
    props: ['metric' => '实时并发']
) ?>
```

- **物理隔离**：后台异步拉取时，Air 仅执行并返回 `src/widgets/StatsCard/view.php` 的纯净 HTML，绝不夹带任何页面外部多余标签。
- **零 JS 依赖**：页面输出包含挂起标记时，Air 自动在 `</body>` 前注入一段不到 400 字节的原生微运行时脚本，零外部 npm/node 依赖开箱即用。

---

## API 端点 (`route.php`) 写法

`route.php` 专门处理数据请求。它可以返回一个包含不同 HTTP 方法处理闭包的数组：

```php
<?php

use Meocox\Http\Request;
use Meocox\Http\Response;

return [
    'GET' => function (Request $req): Response {
        return Response::json([
            'users' => [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ],
        ]);
    },

    'POST' => function (Request $req): Response {
        $name = $req->string('name');
        // 保存逻辑...
        return Response::json(['created' => true, 'name' => $name], 201);
    },
];
```

> [!TIP]
> 如果直接返回数组或字符串，Air 会自动将其包装为标准的 JSON 或 Text 响应。
