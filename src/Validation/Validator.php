<?php

declare(strict_types=1);

namespace Meocox\Validation;

use Meocox\Utils\Arr;

/**
 * 零外部依赖轻量高效验证器。
 */
final class Validator
{
    private array $errors = [];
    private array $validated = [];

    public function __construct(
        private array $data,
        private array $rules,
        private array $messages = []
    ) {
    }

    /**
     * 快速构建验证器实例。
     */
    public static function make(array $data, array $rules, array $messages = []): self
    {
        return new self($data, $rules, $messages);
    }

    /**
     * 执行验证，若校验失败则抛出包含 422 错误的 ValidationException。
     *
     * @return array 验证通过且清洗后的白名单数据
     * @throws ValidationException
     */
    public function validate(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors);
        }

        return $this->validated();
    }

    /**
     * 检查校验是否失败。
     */
    public function fails(): bool
    {
        $this->errors = [];
        $this->validated = [];

        foreach ($this->rules as $field => $ruleString) {
            $rulesList = is_array($ruleString) ? $ruleString : explode('|', (string) $ruleString);
            $hasField = Arr::has($this->data, $field);
            $value = Arr::get($this->data, $field);

            $isNullable = in_array('nullable', $rulesList, true);
            $isRequired = in_array('required', $rulesList, true);

            if ($isRequired && (!$hasField || $value === null || $value === '')) {
                $this->addError($field, 'required', "The {$field} field is required.");
                continue;
            }

            if ($isNullable && ($value === null || $value === '')) {
                $this->validated[$field] = null;
                continue;
            }

            if (!$hasField) {
                continue;
            }

            foreach ($rulesList as $rule) {
                if ($rule === 'required' || $rule === 'nullable') {
                    continue;
                }

                $ruleName = $rule;
                $ruleParam = null;
                if (str_contains($rule, ':')) {
                    [$ruleName, $ruleParam] = explode(':', $rule, 2);
                }

                if (!$this->checkRule($field, $value, $ruleName, $ruleParam)) {
                    break;
                }
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }

        return !empty($this->errors);
    }

    /**
     * 检查是否全部通过。
     */
    public function passes(): bool
    {
        return !$this->fails();
    }

    /**
     * 获取所有错误信息。
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * 获取验证通过的清洗数据。
     */
    public function validated(): array
    {
        return $this->validated;
    }

    /**
     * 执行具体规则检查。
     */
    private function checkRule(string $field, mixed $value, string $rule, ?string $param): bool
    {
        $valid = match ($rule) {
            'string' => is_string($value),
            'int', 'integer' => is_int($value) || (is_string($value) && ctype_digit(ltrim($value, '-'))),
            'numeric' => is_numeric($value),
            'bool', 'boolean' => is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true),
            'array' => is_array($value),
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false,
            'min' => $this->checkMin($value, $param),
            'max' => $this->checkMax($value, $param),
            'in' => in_array((string) $value, explode(',', (string) $param), true),
            'regex' => is_string($value) && (bool) preg_match($param ?? '//', $value),
            default => true,
        };

        if (!$valid) {
            $msg = $this->messages["{$field}.{$rule}"]
                ?? $this->messages[$field]
                ?? "The {$field} is invalid for rule {$rule}.";
            $this->addError($field, $rule, $msg);
            return false;
        }

        return true;
    }

    private function checkMin(mixed $value, ?string $param): bool
    {
        $threshold = (float) $param;
        if (is_numeric($value)) {
            return (float) $value >= $threshold;
        }
        if (is_string($value)) {
            return mb_strlen($value, 'UTF-8') >= $threshold;
        }
        if (is_array($value)) {
            return count($value) >= $threshold;
        }
        return false;
    }

    private function checkMax(mixed $value, ?string $param): bool
    {
        $threshold = (float) $param;
        if (is_numeric($value)) {
            return (float) $value <= $threshold;
        }
        if (is_string($value)) {
            return mb_strlen($value, 'UTF-8') <= $threshold;
        }
        if (is_array($value)) {
            return count($value) <= $threshold;
        }
        return false;
    }

    private function addError(string $field, string $rule, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}
