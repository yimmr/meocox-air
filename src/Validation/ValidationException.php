<?php

declare(strict_types=1);

namespace Meocox\Validation;

use Meocox\Exceptions\HttpException;
use Throwable;

/**
 * 422 表单校验失败异常。
 */
class ValidationException extends HttpException
{
    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(
        protected array $errors = [],
        string $message = 'The given data was invalid.',
        array $headers = [],
        ?Throwable $previous = null
    ) {
        parent::__construct(422, $message, $headers, $previous);
    }

    /**
     * 获取所有字段的校验错误信息。
     *
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
