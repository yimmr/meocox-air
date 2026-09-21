<?php

declare(strict_types=1);

namespace Meocox\Database;

use Closure;
use Meocox\Utils\Arr;

/**
 * 双模协作（链式构造 + Drizzle RQB 字面量）SQL 查询构造器。
 */
class QueryBuilder
{
    protected array $columns = ['*'];
    protected array $wheres = [];
    protected array $bindings = [];
    protected array $orders = [];
    protected ?int $limitValue = null;
    protected ?int $offsetValue = null;
    protected array $withRelations = [];
    protected array $omits = [];
    /** @var class-string<Model>|null */
    protected ?string $modelClass = null;

    public function __construct(
        protected Connection $connection,
        protected string $table
    ) {
    }

    public function setModelClass(string $modelClass): static
    {
        $this->modelClass = $modelClass;
        return $this;
    }

    /**
     * 选择指定字段。
     */
    public function select(array|string $columns = ['*']): static
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    /**
     * 添加 WHERE 条件（支持闭包嵌套）。
     */
    public function where(string|Closure $column, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): static
    {
        if ($column instanceof Closure) {
            $nested = new static($this->connection, $this->table);
            $column($nested);

            $this->wheres[] = [
                'type' => 'Nested',
                'query' => $nested,
                'boolean' => $boolean,
            ];
            $this->bindings = array_merge($this->bindings, $nested->bindings);
            return $this;
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'Basic',
            'column' => $column,
            'operator' => strtoupper((string) $operator),
            'boolean' => $boolean,
        ];
        $this->bindings[] = $value;

        return $this;
    }

    /**
     * 添加 OR WHERE 条件。
     */
    public function orWhere(string|Closure $column, mixed $operator = null, mixed $value = null): static
    {
        return $this->where($column, $operator, $value, 'OR');
    }

    /**
     * 添加 WHERE IN 条件。
     */
    public function whereIn(string $column, array $values, string $boolean = 'AND', bool $not = false): static
    {
        if (empty($values)) {
            // 空集合处理
            return $this->whereRaw($not ? '1 = 1' : '0 = 1', [], $boolean);
        }

        $this->wheres[] = [
            'type' => $not ? 'NotIn' : 'In',
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean,
        ];
        $this->bindings = array_merge($this->bindings, array_values($values));

        return $this;
    }

    /**
     * 添加 WHERE BETWEEN 条件。
     *
     * @param list<mixed>|array{min?: mixed, max?: mixed} $values
     */
    public function whereBetween(string $column, array $values, string $boolean = 'AND', bool $not = false): static
    {
        $min = $values[0] ?? $values['min'] ?? 0;
        $max = $values[1] ?? $values['max'] ?? 0;

        $this->wheres[] = [
            'type' => $not ? 'NotBetween' : 'Between',
            'column' => $column,
            'boolean' => $boolean,
        ];
        $this->bindings[] = $min;
        $this->bindings[] = $max;

        return $this;
    }

    /**
     * 添加 WHERE NOT BETWEEN 条件。
     */
    public function whereNotBetween(string $column, array $values, string $boolean = 'AND'): static
    {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    /**
     * 声明结果中排除的字段。
     */
    public function omit(array|string $columns): static
    {
        $this->omits = array_merge($this->omits, is_array($columns) ? $columns : func_get_args());
        return $this;
    }

    /**
     * 添加原生 WHERE 条件。
     */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'AND'): static
    {
        $this->wheres[] = [
            'type' => 'Raw',
            'sql' => $sql,
            'boolean' => $boolean,
        ];
        $this->bindings = array_merge($this->bindings, $bindings);

        return $this;
    }

    /**
     * 条件执行构造。
     */
    public function when(mixed $value, callable $callback, ?callable $default = null): static
    {
        if ($value) {
            $callback($this, $value);
        } elseif ($default !== null) {
            $default($this, $value);
        }

        return $this;
    }

    /**
     * 添加排序。
     */
    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC',
        ];

        return $this;
    }

    /**
     * 限制返回条数。
     */
    public function limit(int $limit): static
    {
        $this->limitValue = $limit;
        return $this;
    }

    /**
     * 设置偏移量。
     */
    public function offset(int $offset): static
    {
        $this->offsetValue = $offset;
        return $this;
    }

    /**
     * 声明 Drizzle RQB 风格关联查询。
     */
    public function with(array|string $relations): static
    {
        $this->withRelations = array_merge(
            $this->withRelations,
            is_array($relations) ? $relations : func_get_args()
        );

        return $this;
    }

    /**
     * 解析并应用 Drizzle RQB 风格查询参数（字面量模式）。
     *
     * @param array{
     *     columns?: list<string>,
     *     where?: array<string, mixed>|Closure,
     *     orderBy?: array<string, 'asc'|'desc'|'ASC'|'DESC'>|string,
     *     limit?: int,
     *     offset?: int,
     *     with?: array<string, mixed>,
     * } $params
     */
    public function applyParams(array $params): static
    {
        if (isset($params['columns'])) {
            $this->select($params['columns']);
        }

        if (isset($params['where'])) {
            $this->applyWhereConditions($params['where']);
        }

        if (isset($params['orderBy'])) {
            if (is_array($params['orderBy'])) {
                foreach ($params['orderBy'] as $col => $dir) {
                    $this->orderBy($col, $dir);
                }
            } elseif (is_string($params['orderBy'])) {
                $this->orderBy($params['orderBy']);
            }
        }

        if (isset($params['limit'])) {
            $this->limit((int) $params['limit']);
        }

        if (isset($params['offset'])) {
            $this->offset((int) $params['offset']);
        }

        if (isset($params['with'])) {
            $this->with($params['with']);
        }

        if (isset($params['omit'])) {
            $this->omit((array) $params['omit']);
        }

        return $this;
    }

    /**
     * 递归解析字面量 WHERE 数组。
     */
    protected function applyWhereConditions(array|Closure $where): void
    {
        if ($where instanceof Closure) {
            $this->where($where);
            return;
        }

        foreach ($where as $key => $val) {
            if ($val instanceof Closure) {
                $this->where($val);
                continue;
            }

            if (is_array($val)) {
                // 数组形式：普通 IN 集合 [1, 2, 3]
                if (array_is_list($val)) {
                    $this->whereIn($key, $val);
                } else {
                    foreach ($val as $op => $subVal) {
                        $opLower = strtolower((string) $op);
                        match ($opLower) {
                            'between' => $this->whereBetween($key, (array) $subVal),
                            'notbetween', 'not_between' => $this->whereNotBetween($key, (array) $subVal),
                            'startswith', 'starts_with' => $this->where($key, 'LIKE', addcslashes((string) $subVal, '%_\\') . '%'),
                            'endswith', 'ends_with' => $this->where($key, 'LIKE', '%' . addcslashes((string) $subVal, '%_\\')),
                            'contains' => $this->where($key, 'LIKE', '%' . addcslashes((string) $subVal, '%_\\') . '%'),
                            'in' => $this->whereIn($key, (array) $subVal),
                            'nin', 'notin', 'not_in' => $this->whereIn($key, (array) $subVal, 'AND', true),
                            default => $this->where($key, match ($opLower) {
                                'eq' => '=',
                                'ne', 'neq' => '!=',
                                'gt' => '>',
                                'gte' => '>=',
                                'lt' => '<',
                                'lte' => '<=',
                                'like' => 'LIKE',
                                default => '=',
                            }, $subVal),
                        };
                    }
                }
            } else {
                $this->where($key, '=', $val);
            }
        }
    }

    /**
     * 生成并编译完整 SELECT SQL 语句。
     */
    public function toSql(): string
    {
        $cols = implode(', ', array_map(fn($c) => $c === '*' || str_contains($c, '(') ? $c : "`{$c}`", $this->columns));
        $sql = "SELECT {$cols} FROM `{$this->table}`";

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->compileWheres();
        }

        if (!empty($this->orders)) {
            $orderParts = [];
            foreach ($this->orders as $order) {
                $orderParts[] = "`{$order['column']}` {$order['direction']}";
            }
            $sql .= ' ORDER BY ' . implode(', ', $orderParts);
        }

        if ($this->limitValue !== null) {
            $sql .= " LIMIT {$this->limitValue}";
        }

        if ($this->offsetValue !== null) {
            $sql .= " OFFSET {$this->offsetValue}";
        }

        return $sql;
    }

    /**
     * 编译 WHERE 条件子句。
     */
    protected function compileWheres(): string
    {
        $clauses = [];

        foreach ($this->wheres as $index => $where) {
            $prefix = $index === 0 ? '' : $where['boolean'] . ' ';

            if ($where['type'] === 'Basic') {
                $clauses[] = "{$prefix}`{$where['column']}` {$where['operator']} ?";
            } elseif ($where['type'] === 'In' || $where['type'] === 'NotIn') {
                $op = $where['type'] === 'In' ? 'IN' : 'NOT IN';
                $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                $clauses[] = "{$prefix}`{$where['column']}` {$op} ({$placeholders})";
            } elseif ($where['type'] === 'Between' || $where['type'] === 'NotBetween') {
                $op = $where['type'] === 'Between' ? 'BETWEEN' : 'NOT BETWEEN';
                $clauses[] = "{$prefix}`{$where['column']}` {$op} ? AND ?";
            } elseif ($where['type'] === 'Raw') {
                $clauses[] = "{$prefix}{$where['sql']}";
            } elseif ($where['type'] === 'Nested') {
                $nestedSql = $where['query']->compileWheres();
                $clauses[] = "{$prefix}({$nestedSql})";
            }
        }

        return implode(' ', $clauses);
    }

    /**
     * 获取绑定的参数。
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * 执行查询并返回多条结果数组（若存在 with 则自动批量装配关联）。
     */
    public function get(): array
    {
        $rows = $this->connection->query($this->toSql(), $this->bindings);

        if ($this->modelClass !== null) {
            $casts = $this->modelClass::getCasts();
            foreach ($rows as &$row) {
                $row = Hydrator::castOutbound($row, $casts);
            }
            unset($row);
        }

        if (!empty($this->withRelations) && $this->modelClass !== null) {
            $rows = $this->loadRelations($rows);
        }

        if (!empty($this->omits)) {
            foreach ($rows as &$row) {
                foreach ($this->omits as $omitCol) {
                    unset($row[$omitCol]);
                }
            }
            unset($row);
        }

        return $rows;
    }

    /**
     * 获取单条记录。
     */
    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();

        return $results[0] ?? null;
    }

    /**
     * 统计总数。
     */
    public function count(string $columns = '*'): int
    {
        $originalCols = $this->columns;
        $this->columns = ["COUNT({$columns}) AS aggregate"];

        $row = $this->connection->query($this->toSql(), $this->bindings);
        $this->columns = $originalCols;

        return (int) ($row[0]['aggregate'] ?? 0);
    }

    /**
     * 判断记录是否存在。
     */
    public function exists(): bool
    {
        return $this->first() !== null;
    }

    /**
     * 标准 Offset 分页查询。
     */
    public function paginate(int $page = 1, int $perPage = 15): Paginator
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $total = $this->count();

        $this->offset(($page - 1) * $perPage)->limit($perPage);
        $items = $this->get();

        return new Paginator($items, $total, $perPage, $page);
    }

    /**
     * 插入新记录并返回自增 ID。
     */
    public function insert(array $values): int|string
    {
        $columns = array_keys($values);
        $quotedCols = implode(', ', array_map(fn($c) => "`{$c}`", $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        $sql = "INSERT INTO `{$this->table}` ({$quotedCols}) VALUES ({$placeholders})";
        $this->connection->execute($sql, array_values($values));

        return $this->connection->lastInsertId();
    }

    /**
     * 更新符合条件的记录并返回受影响行数。
     */
    public function update(array $values): int
    {
        $setClauses = [];
        $bindings = [];

        foreach ($values as $col => $val) {
            $setClauses[] = "`{$col}` = ?";
            $bindings[] = $val;
        }

        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $setClauses);

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->compileWheres();
            $bindings = array_merge($bindings, $this->bindings);
        }

        return $this->connection->execute($sql, $bindings);
    }

    /**
     * 删除符合条件的记录并返回受影响行数。
     */
    public function delete(): int
    {
        $sql = "DELETE FROM `{$this->table}`";

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->compileWheres();
        }

        return $this->connection->execute($sql, $this->bindings);
    }

    /**
     * Drizzle RQB 风格关系批量内存装配引擎：
     * 避免 N+1 查询，消除 JOIN 笛卡尔积爆炸。
     */
    protected function loadRelations(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $relationsDef = $this->modelClass::getRelations();

        foreach ($this->withRelations as $relationName => $relationConfig) {
            if (is_int($relationName)) {
                $relationName = $relationConfig;
                $relationConfig = true;
            }

            if (!isset($relationsDef[$relationName])) {
                continue;
            }

            $def = $relationsDef[$relationName];
            $type = $def['type'] ?? 'hasMany';
            /** @var class-string<Model> $targetModel */
            $targetModel = $def['model'];
            $foreignKey = $def['foreignKey'];
            $localKey = $def['localKey'] ?? 'id';

            // 提取父级所有关联键值
            $parentKeys = array_values(array_unique(array_filter(array_column($rows, $localKey))));
            if (empty($parentKeys)) {
                continue;
            }

            // 构建子查询
            $subQuery = $targetModel::query()->whereIn($foreignKey, $parentKeys);
            if (is_array($relationConfig)) {
                $subQuery->applyParams($relationConfig);
            }

            $children = $subQuery->get();

            // 按外键建立索引字典
            $grouped = [];
            foreach ($children as $child) {
                $fkVal = $child[$foreignKey] ?? null;
                if ($fkVal !== null) {
                    if ($type === 'hasOne' || $type === 'belongsTo') {
                        $grouped[$fkVal] = $child;
                    } else {
                        $grouped[$fkVal][] = $child;
                    }
                }
            }

            // 回填到父级数据中
            foreach ($rows as &$row) {
                $lkVal = $row[$localKey] ?? null;
                if ($lkVal !== null && isset($grouped[$lkVal])) {
                    $row[$relationName] = $grouped[$lkVal];
                } else {
                    $row[$relationName] = ($type === 'hasOne' || $type === 'belongsTo') ? null : [];
                }
            }
            unset($row);
        }

        return $rows;
    }
}
