<?php

declare(strict_types=1);

namespace Meocox\Event;

/**
 * 强类型轻量事件调度器。
 */
final class Dispatcher
{
    /** @var array<string, list<callable|string>> */
    private array $listeners = [];

    /**
     * 注册事件监听器。
     */
    public function listen(string $event, callable|string $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    /**
     * 触发分发事件。
     *
     * @return list<mixed> 监听器执行返回值集合
     */
    public function dispatch(object|string $event, mixed $payload = null): array
    {
        $name = is_object($event) ? $event::class : $event;
        $eventData = is_object($event) ? $event : $payload;

        if (!isset($this->listeners[$name])) {
            return [];
        }

        $responses = [];

        foreach ($this->listeners[$name] as $listener) {
            if (is_callable($listener)) {
                $responses[] = $listener($eventData);
            } elseif (is_string($listener) && class_exists($listener)) {
                $instance = new $listener();
                if ($instance instanceof ListenerInterface) {
                    $responses[] = $instance->handle($name, $eventData);
                }
            }
        }

        return $responses;
    }

    /**
     * 移除特定事件的所有监听。
     */
    public function forget(string $event): void
    {
        unset($this->listeners[$event]);
    }
}
