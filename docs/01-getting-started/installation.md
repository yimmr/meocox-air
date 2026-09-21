# 环境要求与安装

Meocox Air 专为现代 PHP 生态设计，坚持 100% 零第三方依赖。

---

## 系统环境要求

- **PHP 8.2.0** 或更高版本（深度利用 `readonly class`、DNF 类型、枚举与 First-class callables）
- **核心必要 PHP 扩展**：
  - `pdo` 及驱动（`pdo_mysql` 用于生产环境数据库，或 `pdo_sqlite` 用于轻量场景与单元测试）
  - `mbstring`（多字节字符串与中文/Unicode 支持）
  - `json`（JSON 编解码与 API 数据交互）

> [!NOTE]
> Meocox Air 内部**零第三方依赖**（没有臃肿的 `vendor/` 依赖树，无 `psr/*` 接口包绑定），直接使用 PHP 原生高性能标准库。

---

## 安装方式

### 1. 作为项目子包引入 (Monorepo 推荐)

在项目根目录 `composer.json` 中配置路径映射：

```json
{
    "require": {
        "php": ">=8.2",
        "meocox/air": "*"
    },
    "repositories": [
        {
            "type": "path",
            "url": "packages/meocox-air"
        }
    ]
}
```

执行生成自动加载映射：
```bash
composer dump-autoload
```

### 2. 作为独立库引入

如果将 Meocox Air 发布在私有或开源 Git 仓库中：

```bash
composer require meocox/air
```

---

## 验证安装

创建一个入口验证脚本：

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Meocox\Air;

echo 'Meocox Air Version: ' . Air::version() . PHP_EOL;
```

运行输出：
```text
Meocox Air Version: 0.1.0
```
