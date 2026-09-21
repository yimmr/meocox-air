<?php

declare(strict_types=1);

namespace Meocox\Exceptions;

use RuntimeException;
use Throwable;

/**
 * HTTP 基础异常。
 */
class HttpException extends RuntimeException implements AirThrowable
{
    public function __construct(
        protected int $statusCode = 500,
        string $message = '',
        protected array $headers = [],
        ?Throwable $previous = null,
        int $code = 0
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
