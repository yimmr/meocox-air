<?php

declare(strict_types=1);

namespace Meocox;

/**
 * Meocox Air 核心引擎入口
 */
class Air
{
    public const VERSION = '0.1.0';

    protected static ?self $instance = null;

    protected array $bindings = [];

    public static function instance(): self
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function version(): string
    {
        return self::VERSION;
    }

    public function bind(string $key, mixed $value): void
    {
        $this->bindings[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->bindings[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->bindings);
    }
}
