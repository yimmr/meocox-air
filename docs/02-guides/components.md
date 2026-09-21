# 单文件原生组件系统（Components）

Meocox Air 提供了一套**纯 PHP 文件驱动、零样板代码、100% IDE 智能感知**的原生组件系统。它无需编写冗长的 PHP 类或继承基类，单个 `.php` 文件即为一个完整的可复用组件。

---

## 一、双模式执行设计

针对高频循环渲染与字符串拼装的不同需求，Meocox Air 提供了精准分离的双模式：

### 1. 直接输出模式（推荐日常使用）：`View::component()`
在页面、母版或循环中直接调用，**0 `ob_*` 缓冲开销**，内容直接写入当前的父级输出流中，性能极致：

```php
<?php
// 直接输出到页面
View::component('user-badge', $currentUser);

// 或使用全局简写函数
component('user-badge', $currentUser);
?>
```

### 2. 获取 HTML 字符串模式：`View::componentHTML()`
在需要将组件 HTML 存入变量、进行字符串拼接、页面缓存或作为参数传递时调用：

```php
<?php
// 返回纯 HTML 字符串
$html = View::componentHTML('user-badge', $currentUser);

// 或使用全局简写函数
$html = componentHTML('user-badge', $currentUser);
?>
```
> [!NOTE]
> `componentHTML` 内部使用 `ob_start() / ob_get_clean()` 捕获输出，并内置了异常安全保障：若组件执行抛出异常，内核会自动清空残缺缓冲再抛出，杜绝破损 HTML 污染。

---

## 二、编写一个组件文件

组件通常存放在 `src/components/` 目录下（如 `src/components/user-badge.php`）。

### 1. Props 传递与自动解构
调用时传入的关联数组（如 DB 查询结果或 `Auth::user()`）会被**自动解构为同名原生变量**（使用 `extract($props, EXTR_SKIP)`），同时组件内保留原生的 `$props` 数组：

```php
<!-- src/components/user-badge.php -->
<?php
/**
 * @var array<string, mixed> $props 原始参数数组
 * @var string|null $username 解构出的参数
 * @var string|null $role
 */
$name = $username ?? ($user['username'] ?? '访客');
$userRole = strtoupper($role ?? ($user['role'] ?? 'MEMBER'));
?>
<div class="user-badge">
    <strong><?= htmlspecialchars($name) ?></strong>
    <span class="badge"><?= htmlspecialchars($userRole) ?></span>
</div>
```

在页面中使用：
```php
<?php View::component('user-badge', ['username' => 'Simon', 'role' => 'admin']); ?>

<!-- 也可以直接把数据库/会话模型数组完整传给组件 -->
<?php View::component('user-badge', Auth::user()); ?>
```

---

## 三、组件查找机制与层级语法

支持**点号语法**（Dot Notation）和**斜杠路径**，便于组织深层分类组件：

```php
// 以下等价：查找 src/components/ui/button.php
View::component('ui.button', ['type' => 'submit']);
View::component('ui/button', ['type' => 'submit']);
```

### 探查路径优先级
内核按以下顺序寻找物理文件：
1. `meocox.config.php` 中配置的 `paths.components` 根目录：
   - `{paths.components}/{$name}.php`
   - `{paths.components}/{$name}/view.php`
   - `{paths.components}/{$name}/index.php`
2. 项目约定默认目录：
   - `{project_root}/src/components/{$name}.php`
   - `{project_root}/src/components/{$name}/view.php`
   - `{project_root}/components/{$name}.php`
   - `{project_root}/src/widgets/{$name}/view.php`

---

## 四、自定义组件根目录

在项目的 `meocox.config.php` 中：

```php
use Meocox\Config;

return Config::define([
    'paths' => [
        'routes' => __DIR__ . '/app',
        'components' => __DIR__ . '/src/components', // 自定义组件根目录
    ],
]);
```

---

## 五、彻底告别 `$this->` 与 100% IDE 感知

在旧版模板中，`$this->slot()` 或 `$this->suspense()` 在现代 IDE（PhpStorm、VS Code Intelephense）中无法识别类型。通过静态 `View` 门面与内核生成的 `_ide_helper.php`，开发者可以获得全链路强类型智能提示：

```php
<!-- app/layout.php -->
<main>
    <?= View::slot() ?> <!-- 母版插槽输出 -->
</main>

<!-- app/dashboard/page.php -->
<?= View::suspense('StatsCard', props: ['metric' => '实时负载']) ?>

<?php View::component('user-badge', $currentUser); ?>
```
