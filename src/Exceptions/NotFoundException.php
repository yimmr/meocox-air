<?php

declare(strict_types=1);

namespace Meocox\Exceptions;

use Throwable;

/**
 * 404 资源或路由未找到异常。
 */
class NotFoundException extends HttpException
{
    public function __construct(
        string $message = 'Not Found',
        array $headers = [],
        ?Throwable $previous = null
    ) {
        parent::__construct(404, $message, $headers, $previous);
    }
}
