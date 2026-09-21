<?php

declare(strict_types=1);

namespace Meocox\Container;

use Closure;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

/**
 * 零外部依赖的轻量反射自动装配依赖注入容器。
 */
class Container
{
    private static ?Container $instance = null;
    /** @var array<string, array{concrete: mixed, shared: bool}> */
    protected array $bindings = [];
    /** @var array<string, mixed> */
    protected array $instances = [];
    /** @var array<string, string> 别名映射 [alias => abstract] */
    protected array $aliases = [];

    public static function getInstance(): static
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    public static function setInstance(?Container $container): void
    {
        self::$instance = $container;
    }

    /**
     * 为已绑定的服务注册别名。
     */
    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * 检查容器中是否存在指定服务、绑定或类。
     */
    public function has(string $abstract): bool
    {
        $target = $this->aliases[$abstract] ?? $abstract;

        return isset($this->instances[$target])
            || isset($this->bindings[$target])
            || class_exists($target);
    }

    /**
     * 移除已解析的单例实例缓存。
     */
    public function forget(string $abstract): void
    {
        $target = $this->aliases[$abstract] ?? $abstract;
        unset($this->instances[$target]);
    }

    /**
     * 清空容器所有绑定、实例与别名。
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->aliases = [];
    }

    /**
     * 绑定接口或标识符到实现。
     */
    public function bind(string $abstract, mixed $concrete = null, bool $shared = false): void
    {
        $concrete ??= $abstract;
        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared,
        ];
    }

    /**
     * 注册单例绑定。
     */
    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * 直接绑定已有实例。
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * 解析并构建目标对象。
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        $abstract = $this->aliases[$abstract] ?? $abstract;

        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->bindings[$abstract]['concrete'] ?? $abstract;

        if ($concrete instanceof Closure) {
            $object = $concrete($this, $parameters);
        } elseif (is_string($concrete)) {
            $object = $this->build($concrete, $parameters);
        } else {
            $object = $concrete;
        }

        if (!empty($this->bindings[$abstract]['shared'])) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * 通过反射自动注入参数并实例化类。
     */
    protected function build(string $concrete, array $parameters = []): object
    {
        if (!class_exists($concrete)) {
            throw new RuntimeException("Target class [{$concrete}] does not exist.");
        }

        $reflector = new ReflectionClass($concrete);

        if (!$reflector->isInstantiable()) {
            throw new RuntimeException("Target [{$concrete}] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();
        if ($constructor === null) {
            return new $concrete();
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters(), $parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * 调用任意 Callable，自动依赖注入其所需参数。
     */
    public function call(callable|array $callable, array $parameters = []): mixed
    {
        if (is_array($callable)) {
            [$classOrObject, $method] = $callable;
            $object = is_string($classOrObject) ? $this->make($classOrObject) : $classOrObject;
            $reflector = new ReflectionMethod($object, $method);
            $dependencies = $this->resolveDependencies($reflector->getParameters(), $parameters);

            return $reflector->invokeArgs($object, $dependencies);
        }

        $reflector = new ReflectionFunction(Closure::fromCallable($callable));
        $dependencies = $this->resolveDependencies($reflector->getParameters(), $parameters);

        return $callable(...$dependencies);
    }

    /**
     * 解析方法或构造函数形参列表。
     *
     * @param list<ReflectionParameter> $params
     */
    protected function resolveDependencies(array $params, array $parameters = []): array
    {
        $dependencies = [];

        foreach ($params as $param) {
            $name = $param->getName();

            // 1. 优先使用用户显式传递的实参
            if (array_key_exists($name, $parameters)) {
                $dependencies[] = $parameters[$name];
                continue;
            }

            // 2. 根据类型提示从容器中递归自动装配
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();
                if ($typeName === 'self') {
                    $declaringClass = $param->getDeclaringClass();
                    $typeName = $declaringClass !== null ? $declaringClass->getName() : $typeName;
                } elseif ($typeName === 'parent') {
                    $parentClass = $param->getDeclaringClass()?->getParentClass();
                    $typeName = $parentClass !== false && $parentClass !== null ? $parentClass->getName() : $typeName;
                }

                $dependencies[] = $this->make($typeName);
                continue;
            }

            // 3. 检查默认值
            if ($param->isDefaultValueAvailable()) {
                $dependencies[] = $param->getDefaultValue();
                continue;
            }

            if ($param->allowsNull()) {
                $dependencies[] = null;
                continue;
            }

            $declaring = $param->getDeclaringClass()?->getName() ?? $param->getDeclaringFunction()->getName();
            throw new RuntimeException("Unable to resolve dependency [{$param->getName()}] in [{$declaring}].");
        }

        return $dependencies;
    }
}
