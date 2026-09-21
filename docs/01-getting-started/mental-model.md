# 设计哲学与心智模型

了解 Meocox Air 的底层心智模型，有助于写出极简、高性能且易于维护的代码。

---

## 1. 被动库姿态 (Passive Library)

传统 PHP 框架（如 Laravel、Symfony）多为**主动型统治者（Active Framework）**：
- 它接管一切启动流程、异常处理与生命周期，要求你的代码适配框架。
- 若试图将这类框架塞入 WordPress、Workerman 或微服务中，会产生沉重的阻抗失配。

**Meocox Air 采用“被动库姿态”：**
- 内核代码中**没有任何特定宿主环境的强绑定**（不依赖 `defined('ABSPATH')`，不强依赖全局超级全局变量，不劫持全局未捕获异常）。
- 宿主（WordPress 插件/主题、独立 CLI、微服务或 Swoole）仅作为“容器”来驱动 Air。
- Air 可以轻松嵌入任何现有 PHP 项目中，甚至只单独借用其数据库 RQB 模块或文件路由。

---

## 2. 零外部依赖 (Zero Dependencies)

依赖地狱（Dependency Hell）和版本冲突是 PHP 长期维护项目的最大痛点。
- Air 要求最低 PHP 8.2+，全面采用现代语言特性（DNF 类型、match 表达式、readonly 等），**不再需要通过安装 polyfill 或第三方包来弥补语言能力的不足**。
- 不引入任何外部 composer 依赖，连 `psr/*` 接口包也不依赖，从物理根源上消除了版本冲突、依赖安全审计漏洞和臃肿的 `vendor/` 体积。

---

## 3. Zero-Cost 运行时哲学

在 PHP-FPM 无状态生命周期中，“每次请求都要重复解析”是性能杀手。Air 践行 Zero-Cost 哲学：

| 领域 | 传统方案弊端 | Meocox Air 解决方案 |
| :--- | :--- | :--- |
| **ORM 元数据** | 每次请求通过反射或动态分析读取 Annotations/Attributes，带来大量对象分配 | **JIT Schema 编译器**：首次运行自动提取 Schema 编译为纯 PHP 数组，由 OPcache 驻留共享内存，运行时 0 纳秒、0 分配 |
| **数据映射** | 笨重的 ActiveRecord，每个字段封装成大对象 | **轻量 DTO / 纯数组**：Hydrator 采用倒置循环与 match 语句，微秒级极速清洗 |
| **关系查询** | 传统 JOIN 产生庞大的笛卡尔积冗余行；或者懒加载导致 N+1 查询风暴 | **Drizzle RQB 风格**：批量收集外键 -> 单次 `WHERE IN` -> 内存树形拼装 |
| **后置副作用** | 用户发起请求后，阻塞等待邮件/微信发送完毕才返回 HTTP 响应 | **`Air::after()`**：原生利用 `fastcgi_finish_request()`，5ms 内冲刷响应给用户，后台异步继续干活 |
