# Meocox Air 官方文档

欢迎查阅 **Meocox Air** 开发者指南与 API 参考手册。

Meocox Air 是一个纯粹、现代且零外部依赖的 **PHP 8.2+** 核心基础框架库。它融合了 **Next.js 16**（文件约定路由、BFF 架构、三级母版、`after()` 后置异步任务）与 **Drizzle ORM**（单一事实源、声明式 RQB 关系查询、JIT 模式零运行时开销编译）的优秀工程设计哲学。

---

## 快速导航索引

### 1. 起步 (Getting Started)
- [环境安装与准备](file:///home/imoncn/org/packages/meocox-air/docs/01-getting-started/installation.md) - PHP 8.2+ 运行要求与 Composer 自动加载
- [项目配置文件 (meocox.config.php)](file:///home/imoncn/org/packages/meocox-air/docs/01-getting-started/configuration.md) - 路径重写、重定向、响应头与母版规则
- [核心设计哲学与心智模型](file:///home/imoncn/org/packages/meocox-air/docs/01-getting-started/mental-model.md) - 被动库姿态、零外部依赖与 Zero-Cost 运行时

### 2. 核心功能指南 (Guides)
- **文件约定路由 (Routing)**
  - [端点文件约定](file:///home/imoncn/org/packages/meocox-air/docs/02-guides/routing/file-conventions.md) - `page.php`, `route.php`, `_private/`
  - [三级母版布局体系](file:///home/imoncn/org/packages/meocox-air/docs/02-guides/routing/layouts.md) - 配置覆写 > 专有命名母版 > 递归级联
  - [目录门禁守卫](file:///home/imoncn/org/packages/meocox-air/docs/02-guides/routing/guards.md) - `guard.php` 鉴权与前置拦截
- **HTTP 引擎与生命周期 (HTTP Engine)**
  - [请求与响应对象](file:///home/imoncn/org/packages/meocox-air/docs/02-guides/http/request-response.md) - 强类型获取、防污染与流式输出
  - [洋葱模型中间件](file:///home/imoncn/org/packages/meocox-air/docs/02-guides/http/middleware.md) - 管道机制与全局中间件
  - [异步副作用任务 (Air::after)](file:///home/imoncn/org/packages/meocox-air/docs/02-guides/http/after-tasks.md) - 5ms 响应与后台非阻塞任务
- **现代数据库核心 (Database & RQB)**
  - [单一事实源与 JIT 架构](file:///home/imoncn/org/packages/meocox-air/docs/02-guides/database/schema-and-jit.md) - Table 蓝图与 OPcache 零开销常驻
  - [Drizzle RQB 风格关联查询](file:///home/imoncn/org/packages/meocox-air/docs/02-guides/database/rqb-queries.md) - 内存树形批量拼装，彻底消灭笛卡尔积
  - [嵌套事务与安全审计](file:///home/imoncn/org/packages/meocox-air/docs/02-guides/database/transactions.md) - Savepoints 事务与慢查询监听
- **业务安全与辅助 (Auth & Validation)**
  - [声明式解耦鉴权 (DAL)](file:///home/imoncn/org/packages/meocox-air/docs/02-guides/auth-and-validation/auth-dal.md) - 100% 独立于宿主登录态
  - [数据校验与异常处理](file:///home/imoncn/org/packages/meocox-air/docs/02-guides/auth-and-validation/validation.md) - 白名单清洗与 422 校验机制

### 3. API 参考手册 (API Reference)
- [Air 核心生命周期](file:///home/imoncn/org/packages/meocox-air/docs/03-api-reference/air.md)
- [Config 配置中心](file:///home/imoncn/org/packages/meocox-air/docs/03-api-reference/config.md)
- [Request 请求对象](file:///home/imoncn/org/packages/meocox-air/docs/03-api-reference/request.md)
- [Response 响应对象](file:///home/imoncn/org/packages/meocox-air/docs/03-api-reference/response.md)
- [DB 数据库门面](file:///home/imoncn/org/packages/meocox-air/docs/03-api-reference/db.md)
- [QueryBuilder 查询构造器](file:///home/imoncn/org/packages/meocox-air/docs/03-api-reference/query-builder.md)
- [Model 数据模型基类](file:///home/imoncn/org/packages/meocox-air/docs/03-api-reference/model.md)
- [Validator 规则校验器](file:///home/imoncn/org/packages/meocox-air/docs/03-api-reference/validator.md)

---

## 核心设计理念

> [!TIP]
> **被动库原则（Passive Library）**
> Meocox Air 坚决不包含任何宿主特定代码（如 `defined('ABSPATH')`）。无论部署在独立 CLI、微服务，还是作为 WordPress 主题/插件底座，宿主环境只需提供引导入口，Air 始终保持纯粹与高度可测试。
