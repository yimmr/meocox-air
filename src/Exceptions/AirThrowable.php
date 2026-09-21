<?php

declare(strict_types=1);

namespace Meocox\Exceptions;

use Throwable;

/**
 * 顶层异常接口，所有 Meocox Air 框架抛出的异常均实现此接口。
 */
interface AirThrowable extends Throwable
{
}
