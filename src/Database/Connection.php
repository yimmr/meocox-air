<?php

declare(strict_types=1);

namespace Meocox\Database;

use Meocox\Config;
use Meocox\Utils\Log;
use PDO;
use PDOStatement;
use Throwable;

/**
 * 现代强类型 PDO 数据库连接与事务管理器。
 */
final class Connection
{
    private ?PDO $pdo = null;
    private int $transactionLevel = 0;
    /** @var list<callable> */
    private static array $listeners = [];

    public function __construct(private array $config = [])
    {
    }

    /**
     * 注册全局 SQL 执行监听器。
     */
    public static function listen(callable $listener): void
    {
        self::$listeners[] = $listener;
    }

    /**
     * 获取底层 PDO 实例（懒加载）。
     */
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }

        return $this->pdo;
    }

    /**
     * 创建针对指定表的查询构造器。
     */
    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }

    /**
     * 建立数据库连接。
     */
    private function connect(): void
    {
        $driver = $this->config['driver'] ?? 'mysql';

        if ($driver === 'sqlite') {
            $database = $this->config['database'] ?? ':memory:';
            $dsn = "sqlite:{$database}";
            $this->pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            return;
        }

        // 默认 MySQL 连接
        $host = $this->config['host'] ?? '127.0.0.1';
        $port = $this->config['port'] ?? 3306;
        $database = $this->config['database'] ?? '';
        $charset = $this->config['charset'] ?? 'utf8mb4';
        $username = $this->config['username'] ?? 'root';
        $password = $this->config['password'] ?? '';

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        $this->pdo = new PDO($dsn, $username, $password, $options);
    }

    /**
     * 执行 SELECT 查询并返回全部结果数组。
     */
    public function query(string $sql, array $bindings = []): array
    {
        $stmt = $this->run($sql, $bindings);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * 执行 INSERT / UPDATE / DELETE 操作并返回受影响行数。
     */
    public function execute(string $sql, array $bindings = []): int
    {
        $stmt = $this->run($sql, $bindings);
        return $stmt->rowCount();
    }

    /**
     * 获取最后插入的自增 ID。
     */
    public function lastInsertId(?string $name = null): string|int
    {
        $id = $this->getPdo()->lastInsertId($name);
        return is_numeric($id) ? (int) $id : $id;
    }

    /**
     * 支持多层嵌套 Savepoints 的闭包式安全事务。
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    /**
     * 开启事务（或 Savepoint）。
     */
    public function beginTransaction(): void
    {
        $pdo = $this->getPdo();

        if ($this->transactionLevel === 0) {
            $pdo->beginTransaction();
        } else {
            $pdo->exec("SAVEPOINT trans_{$this->transactionLevel}");
        }

        $this->transactionLevel++;
    }

    /**
     * 提交事务（或释放 Savepoint）。
     */
    public function commit(): void
    {
        if ($this->transactionLevel === 0) {
            return;
        }

        $this->transactionLevel--;
        $pdo = $this->getPdo();

        if ($this->transactionLevel === 0) {
            $pdo->commit();
        } else {
            $pdo->exec("RELEASE SAVEPOINT trans_{$this->transactionLevel}");
        }
    }

    /**
     * 回滚事务（或回滚至 Savepoint）。
     */
    public function rollBack(): void
    {
        if ($this->transactionLevel === 0) {
            return;
        }

        $this->transactionLevel--;
        $pdo = $this->getPdo();

        if ($this->transactionLevel === 0) {
            $pdo->rollBack();
        } else {
            $pdo->exec("ROLLBACK TO SAVEPOINT trans_{$this->transactionLevel}");
        }
    }

    /**
     * 底层执行预处理语句并监控执行耗时。
     */
    private function run(string $sql, array $bindings = []): PDOStatement
    {
        $start = microtime(true);
        $pdo = $this->getPdo();

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);

        $duration = (microtime(true) - $start) * 1000; // 毫秒

        // 慢查询日志触发
        $threshold = Config::get('database.slowQueryThreshold', 500);
        if ($duration >= $threshold) {
            Log::warning(sprintf('Slow Query Detected (%.2fms): %s', $duration, $sql), [
                'bindings' => $bindings,
            ]);
        }

        // 触发监听器
        foreach (self::$listeners as $listener) {
            $listener($sql, $bindings, $duration);
        }

        return $stmt;
    }
}
