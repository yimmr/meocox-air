<?php

declare(strict_types=1);

namespace Meocox\Database;

/**
 * 数据表蓝图定义器（用于声明式 Schema 定义与 DDL 自动生成）。
 */
final class Table
{
    /** @var array<string, array{
     *     type: string,
     *     length?: int|null,
     *     precision?: int|null,
     *     scale?: int|null,
     *     nullable: bool,
     *     default?: mixed,
     *     autoIncrement?: bool,
     *     primary?: bool,
     *     unique?: bool,
     *     index?: bool,
     *     comment?: string,
     *     values?: array<string>,
     *     cast?: string,
     * }> */
    private array $columns = [];
    private array $indexes = [];
    private ?string $primaryKey = null;

    /**
     * 自增主键 ID (BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY)。
     */
    public function id(string $name = 'id'): self
    {
        $this->columns[$name] = [
            'type' => 'bigint',
            'nullable' => false,
            'autoIncrement' => true,
            'primary' => true,
            'cast' => 'int',
        ];
        $this->primaryKey = $name;

        return $this;
    }

    /**
     * 字符串列 (VARCHAR)。
     */
    public function string(string $name, int $length = 255): self
    {
        $this->columns[$name] = [
            'type' => 'varchar',
            'length' => $length,
            'nullable' => false,
            'cast' => 'string',
        ];

        return $this;
    }

    /**
     * 整数列 (INT)。
     */
    public function integer(string $name): self
    {
        $this->columns[$name] = [
            'type' => 'int',
            'nullable' => false,
            'cast' => 'int',
        ];

        return $this;
    }

    /**
     * 大整数列 (BIGINT)。
     */
    public function bigInteger(string $name): self
    {
        $this->columns[$name] = [
            'type' => 'bigint',
            'nullable' => false,
            'cast' => 'int',
        ];

        return $this;
    }

    /**
     * 长文本列 (TEXT)。
     */
    public function text(string $name): self
    {
        $this->columns[$name] = [
            'type' => 'text',
            'nullable' => false,
            'cast' => 'string',
        ];

        return $this;
    }

    /**
     * 金额高精度定点数列 (DECIMAL)。
     * 严格映射为 PHP string，由 bcmath 保障财务安全。
     */
    public function decimal(string $name, int $precision = 14, int $scale = 4): self
    {
        $this->columns[$name] = [
            'type' => 'decimal',
            'precision' => $precision,
            'scale' => $scale,
            'nullable' => false,
            'default' => '0.0000',
            'cast' => 'decimal',
        ];

        return $this;
    }

    /**
     * 布尔列 (TINYINT(1))。
     */
    public function boolean(string $name): self
    {
        $this->columns[$name] = [
            'type' => 'tinyint',
            'length' => 1,
            'nullable' => false,
            'default' => 0,
            'cast' => 'bool',
        ];

        return $this;
    }

    /**
     * JSON 列。
     */
    public function json(string $name): self
    {
        $this->columns[$name] = [
            'type' => 'json',
            'nullable' => false,
            'cast' => 'json',
        ];

        return $this;
    }

    /**
     * 枚举列。
     *
     * @param class-string<\BackedEnum>|array<string> $enumClassOrValues
     */
    public function enum(string $name, string|array $enumClassOrValues): self
    {
        $values = [];
        $cast = 'string';

        if (is_string($enumClassOrValues) && enum_exists($enumClassOrValues)) {
            $cast = 'enum:' . $enumClassOrValues;
            foreach ($enumClassOrValues::cases() as $case) {
                $values[] = (string) $case->value;
            }
        } elseif (is_array($enumClassOrValues)) {
            $values = $enumClassOrValues;
        }

        $this->columns[$name] = [
            'type' => 'enum',
            'values' => $values,
            'nullable' => false,
            'cast' => $cast,
        ];

        return $this;
    }

    /**
     * 日期时间列 (DATETIME)。
     */
    public function datetime(string $name): self
    {
        $this->columns[$name] = [
            'type' => 'datetime',
            'nullable' => false,
            'cast' => 'string',
        ];

        return $this;
    }

    /**
     * 快捷添加 created_at 与 updated_at 时间戳列。
     */
    public function timestamps(): self
    {
        $this->datetime('created_at')->nullable();
        $this->datetime('updated_at')->nullable();

        return $this;
    }

    /**
     * 将最后声明的列设为允许 NULL。
     */
    public function nullable(): self
    {
        $last = array_key_last($this->columns);
        if ($last !== null) {
            $this->columns[$last]['nullable'] = true;
        }

        return $this;
    }

    /**
     * 为最后声明的列设置默认值。
     */
    public function default(mixed $value): self
    {
        $last = array_key_last($this->columns);
        if ($last !== null) {
            $this->columns[$last]['default'] = $value;
        }

        return $this;
    }

    /**
     * 为最后声明的列添加唯一索引。
     */
    public function unique(): self
    {
        $last = array_key_last($this->columns);
        if ($last !== null) {
            $this->columns[$last]['unique'] = true;
            $this->indexes['uniq_' . $last] = ['type' => 'unique', 'columns' => [$last]];
        }

        return $this;
    }

    /**
     * 为最后声明的列添加普通索引。
     */
    public function index(): self
    {
        $last = array_key_last($this->columns);
        if ($last !== null) {
            $this->columns[$last]['index'] = true;
            $this->indexes['idx_' . $last] = ['type' => 'index', 'columns' => [$last]];
        }

        return $this;
    }

    /**
     * 为最后声明的列添加注释说明。
     */
    public function comment(string $comment): self
    {
        $last = array_key_last($this->columns);
        if ($last !== null) {
            $this->columns[$last]['comment'] = $comment;
        }

        return $this;
    }

    /**
     * 添加复合索引。
     */
    public function addIndex(string $name, array $columns): self
    {
        $this->indexes[$name] = ['type' => 'index', 'columns' => $columns];
        return $this;
    }

    /**
     * 获取所有字段元数据。
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * 获取主键列名。
     */
    public function getPrimaryKey(): ?string
    {
        return $this->primaryKey;
    }

    /**
     * 自动从字段定义推导运行时类型转换规则 (Casts)。
     */
    public function getCasts(): array
    {
        $casts = [];
        foreach ($this->columns as $name => $col) {
            if (isset($col['cast'])) {
                $casts[$name] = $col['cast'];
            }
        }

        return $casts;
    }

    /**
     * 生成标准建表 DDL SQL (默认针对 MySQL 8+)。
     */
    public function toSql(string $tableName): string
    {
        $lines = [];

        foreach ($this->columns as $name => $col) {
            $typeStr = match ($col['type']) {
                'varchar' => "VARCHAR({$col['length']})",
                'int' => "INT",
                'bigint' => "BIGINT",
                'tinyint' => "TINYINT({$col['length']})",
                'decimal' => "DECIMAL({$col['precision']},{$col['scale']})",
                'text' => "TEXT",
                'json' => "JSON",
                'datetime' => "DATETIME",
                'enum' => "ENUM('" . implode("','", array_map('addslashes', $col['values'] ?? [])) . "')",
                default => strtoupper($col['type']),
            };

            $sql = "`{$name}` {$typeStr}";

            if (!empty($col['autoIncrement'])) {
                $sql .= ' AUTO_INCREMENT';
            }

            $sql .= $col['nullable'] ? ' NULL' : ' NOT NULL';

            if (array_key_exists('default', $col)) {
                $def = $col['default'];
                if ($def === null) {
                    $sql .= ' DEFAULT NULL';
                } elseif (is_numeric($def)) {
                    $sql .= " DEFAULT {$def}";
                } else {
                    $sql .= " DEFAULT '" . addslashes((string) $def) . "'";
                }
            }

            if (!empty($col['comment'])) {
                $sql .= " COMMENT '" . addslashes($col['comment']) . "'";
            }

            $lines[] = '  ' . $sql;
        }

        if ($this->primaryKey !== null) {
            $lines[] = "  PRIMARY KEY (`{$this->primaryKey}`)";
        }

        foreach ($this->indexes as $indexName => $idx) {
            $cols = implode('`, `', $idx['columns']);
            if ($idx['type'] === 'unique') {
                $lines[] = "  UNIQUE KEY `{$indexName}` (`{$cols}`)";
            } else {
                $lines[] = "  KEY `{$indexName}` (`{$cols}`)";
            }
        }

        $body = implode(",\n", $lines);

        return "CREATE TABLE IF NOT EXISTS `{$tableName}` (\n{$body}\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    }
}
