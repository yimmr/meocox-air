<?php

declare(strict_types=1);

namespace Meocox;

use Meocox\Database\Connection;
use Meocox\Database\QueryBuilder;

/**
 * 数据库全局门面 (DB)。
 */
final class DB
{
    /** @var array<string, Connection> */
    private static array $connections = [];

    /**
     * 获取指定名称的数据库连接（默认取配置 default）。
     */
    public static function connection(?string $name = null): Connection
    {
        $name ??= Config::get('database.default', 'default');

        if (!isset(self::$connections[$name])) {
            $config = Config::get("database.connections.{$name}", []);
            self::$connections[$name] = new Connection($config);
        }

        return self::$connections[$name];
    }

    /**
     * 创建针对指定表的查询构造器。
     */
    public static function table(string $table, ?string $connection = null): QueryBuilder
    {
        return self::connection($connection)->table($table);
    }

    /**
     * 执行带 Savepoint 嵌套支持的安全事务。
     */
    public static function transaction(callable $callback, ?string $connection = null): mixed
    {
        return self::connection($connection)->transaction($callback);
    }

    /**
     * 注册全局 SQL 执行监听器（用于指标度量或调试分析）。
     */
    public static function listen(callable $listener): void
    {
        Connection::listen($listener);
    }

    /**
     * 执行原生 SELECT 查询。
     */
    public static function query(string $sql, array $bindings = [], ?string $connection = null): array
    {
        return self::connection($connection)->query($sql, $bindings);
    }

    /**
     * 执行原生 DML / DDL 并返回受影响行数。
     */
    public static function execute(string $sql, array $bindings = [], ?string $connection = null): int
    {
        return self::connection($connection)->execute($sql, $bindings);
    }

    /**
     * 清理连接池（测试隔离）。
     */
    public static function reset(): void
    {
        self::$connections = [];
    }
}
