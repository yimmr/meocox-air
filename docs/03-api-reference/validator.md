# Validator 规则校验器 API 参考

命名空间：`Meocox\Validation\Validator`

`Validator` 提供轻量规则校验与白名单数据清洗过滤。

---

## 构造与执行

### `Validator::make(array $data, array $rules, array $messages = []): Validator`
快速实例化验证器。
- **参数**：
  - `$data`: 待校验的输入数据字典。
  - `$rules`: 字段与校验规则映射（管道符分隔或数组）。
  - `$messages`: 自定义错误提示消息字典（可选）。

---

### `validate(): array`
执行校验。
- **返回**：仅包含验证通过且清洗后的**白名单数据数组**。
- **异常**：若校验失败，直接抛出 `Meocox\Validation\ValidationException`（包含 HTTP 422 状态码与全部字段错误）。

---

### `passes(): bool`
检查数据是否全部通过验证。

---

### `fails(): bool`
检查数据是否存在校验失败项。

---

### `errors(): array`
获取所有字段的错误信息列表：
```php
[
    'email' => ['The email must be a valid email address.'],
    'password' => ['The password is invalid for rule min.'],
]
```

---

### `validated(): array`
获取验证通过后的白名单干净数据字典。
