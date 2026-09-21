<?php

declare(strict_types=1);

namespace Meocox\Database;

use ArrayAccess;
use JsonSerializable;
use Meocox\DB;
use Meocox\Exceptions\NotFoundException;
use Meocox\Utils\Str;

/**
 * 极简现代数据模型基类（单一事实源 SSOT + 零运行时反射开销）。
 */
abstract class Model implements ArrayAccess, JsonSerializable
{
    protected static ?string $table = null;
    protected static ?string $primaryKey = null;
    protected static ?string $connection = null;
    /** @var array<string, string> 仅老表/WordPress 表需声明 */
    protected static array $casts = [];
    /** @var array<string, array{type: string, model: class-string<Model>, foreignKey: string, localKey?: string}> */
    protected static array $relations = [];

    protected array $attributes = [];
    protected array $original = [];
    protected bool $exists = false;

    public function __construct(array $attributes = [], bool $exists = false)
    {
        $this->attributes = $attributes;
        $this->original = $attributes;
        $this->exists = $exists;
    }

    /**
     * 获取模型关联的数据表名。
     */
    public static function getTable(): string
    {
        if (static::$table !== null) {
            return static::$table;
        }

        $shortName = substr(strrchr(static::class, '\\') ?: static::class, 1);
        return Str::plural(Str::snake($shortName));
    }

    /**
     * 获取主键列名。
     */
    public static function getPrimaryKey(): string
    {
        if (static::$primaryKey !== null) {
            return static::$primaryKey;
        }

        if (method_exists(static::class, 'schema')) {
            $meta = SchemaManager::getMetadata(static::class);
            return $meta['primaryKey'];
        }

        return 'id';
    }

    /**
     * 获取类型转换规则映射。
     */
    public static function getCasts(): array
    {
        if (method_exists(static::class, 'schema')) {
            $meta = SchemaManager::getMetadata(static::class);
            return $meta['casts'];
        }

        return static::$casts;
    }

    /**
     * 获取模型白名单列。
     */
    public static function getWhitelist(): ?array
    {
        if (method_exists(static::class, 'schema')) {
            $meta = SchemaManager::getMetadata(static::class);
            return $meta['whitelist'];
        }

        return null;
    }

    /**
     * 获取模型关联关系定义。
     */
    public static function getRelations(): array
    {
        return static::$relations;
    }

    /**
     * 获取底层的数据库连接。
     */
    public static function getConnection(): Connection
    {
        return DB::connection(static::$connection);
    }

    /**
     * 创建针对当前模型的查询构造器。
     */
    public static function query(): QueryBuilder
    {
        $builder = new QueryBuilder(static::getConnection(), static::getTable());
        $builder->setModelClass(static::class);

        return $builder;
    }

    /**
     * 根据主键查询单条记录。
     */
    public static function find(mixed $id): ?static
    {
        $pk = static::getPrimaryKey();
        $row = static::query()->where($pk, $id)->first();

        if ($row === null) {
            return null;
        }

        return new static($row, true);
    }

    /**
     * 根据主键查询，若不存在则抛出 404 异常。
     */
    public static function findOrFail(mixed $id): static
    {
        $record = static::find($id);
        if ($record === null) {
            throw new NotFoundException("Record not found on model [" . static::class . "] with ID {$id}");
        }

        return $record;
    }

    /**
     * Drizzle RQB 风格批量查询。
     *
     * @param array{
     *     columns?: list<string>,
     *     where?: array<string, mixed>|\Closure,
     *     orderBy?: array<string, string>|string,
     *     limit?: int,
     *     offset?: int,
     *     with?: array<string, mixed>,
     * } $params
     * @return list<static>
     */
    public static function findMany(array $params = []): array
    {
        $rows = static::query()->applyParams($params)->get();

        return array_map(fn($row) => new static($row, true), $rows);
    }

    /**
     * 创建并持久化一条新记录。
     */
    public static function create(array $attributes): static
    {
        $cleanData = Hydrator::prepareForStorage(
            $attributes,
            static::getCasts(),
            static::getWhitelist()
        );

        $id = static::getConnection()->table(static::getTable())->insert($cleanData);
        $pk = static::getPrimaryKey();

        $attributes[$pk] = $id;

        return new static($attributes, true);
    }

    /**
     * 更新当前记录属性。
     */
    public function update(array $attributes): bool
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this->save();
    }

    /**
     * 保存当前记录到数据库。
     */
    public function save(): bool
    {
        $pk = static::getPrimaryKey();

        if ($this->exists) {
            $dirty = array_diff_assoc($this->attributes, $this->original);
            if (empty($dirty)) {
                return true;
            }

            $cleanData = Hydrator::prepareForStorage(
                $dirty,
                static::getCasts(),
                static::getWhitelist()
            );

            $affected = static::query()
                ->where($pk, $this->original[$pk])
                ->update($cleanData);

            $this->original = $this->attributes;
            return $affected > 0;
        }

        $created = static::create($this->attributes);
        $this->attributes = $created->attributes;
        $this->original = $created->original;
        $this->exists = true;

        return true;
    }

    /**
     * 从数据库中删除当前记录。
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $pk = static::getPrimaryKey();
        $affected = static::query()->where($pk, $this->attributes[$pk])->delete();

        $this->exists = false;

        return $affected > 0;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function toJson(): string
    {
        return (string) json_encode($this->attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->attributes[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->attributes[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }
}
