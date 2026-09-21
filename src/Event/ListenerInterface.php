<?php

declare(strict_types=1);

namespace Meocox\Event;

/**
 * 事件监听器契约接口。
 */
interface ListenerInterface
{
    /**
     * 处理触发的事件。
     */
    public function handle(object|string $event, mixed $payload = null): mixed;
}
