# 数据校验与安全白名单 (Validator)

在 Web 开发中，“绝不相信任何用户输入”是第一安全原则。Meocox Air 内置了零外部依赖的高效规则验证器，并遵循**“只返回验证通过的白名单数据”**原则。

---

## 快速校验

使用 `Validator::make` 构造校验器：

```php
use Meocox\Validation\Validator;

$validator = Validator::make($request->all(), [
    'email'    => 'required|email',
    'password' => 'required|string|min:8',
    'age'      => 'nullable|int|min:18',
    'role'     => 'required|in:admin,editor,user',
]);

if ($validator->fails()) {
    $errors = $validator->errors();
    // 返回错误信息
}

// 获取清洗后的白名单数据（未在规则中声明的恶意注入字段自动被过滤）
$cleanData = $validator->validated();
```

---

## 快捷自动抛出异常 (`validate()`)

使用 `validate()` 方法。如果校验失败，将自动抛出 `ValidationException`（包含 HTTP 422 状态码与详细字段错误字典）：

```php
use Meocox\Validation\Validator;

// 若失败直接抛出 ValidationException
$data = Validator::make($request->all(), [
    'title' => 'required|string|max:100',
    'price' => 'required|numeric|min:0.01',
])->validate();

// 校验通过，安全入库
Product::create($data);
```

当 `ValidationException` 向上抛出至 `Air::run()` 时，Air 会自动向客户端输出标准的 422 响应：

```json
{
  "error": true,
  "message": "The given data was invalid.",
  "errors": {
    "title": ["The title field is required."],
    "price": ["The price is invalid for rule min."]
  }
}
```

---

## 支持的核心规则

| 规则 | 说明 | 示例 |
| :--- | :--- | :--- |
| `required` | 字段必须存在且非空 | `'name' => 'required'` |
| `nullable` | 字段允许为 null 或空字符串 | `'phone' => 'nullable\|string'` |
| `string` | 必须为字符串 | `'title' => 'string'` |
| `int` / `integer` | 必须为整数或整数字符串 | `'page' => 'int'` |
| `numeric` | 必须为数字类型 | `'amount' => 'numeric'` |
| `bool` / `boolean` | 必须为布尔值或可识别的布尔标量 | `'accept_terms' => 'bool'` |
| `email` | 必须为有效邮箱格式 | `'email' => 'email'` |
| `url` | 必须为有效 URL 网址 | `'website' => 'url'` |
| `min:value` | 字符串/数组长度，或数值最小值 | `'password' => 'min:8'` |
| `max:value` | 字符串/数组长度，或数值最大值 | `'name' => 'max:32'` |
| `in:foo,bar` | 必须处于给定枚举列表中 | `'status' => 'in:active,disabled'` |
| `regex:pattern` | 正则表达式匹配 | `'code' => 'regex:/^[A-Z0-9]{6}$/'` |
