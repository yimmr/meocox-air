<?php

declare(strict_types=1);

namespace Meocox\Http;

use Closure;

/**
 * 洋葱模型中间件管道执行器。
 */
final class Pipeline
{
    private mixed $passable = null;
    private array $pipes = [];

    /**
     * 设置穿过管道的目标对象 (通常为 Request)。
     */
    public function send(mixed $passable): self
    {
        $this->passable = $passable;
        return $this;
    }

    /**
     * 设置要遍历的中间件列表。
     */
    public function through(array $pipes): self
    {
        $this->pipes = $pipes;
        return $this;
    }

    /**
     * 执行管道并返回最终响应。
     *
     * @param callable(mixed): Response $destination
     * @return Response
     */
    public function then(callable $destination): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            fn(mixed $passable): Response => $destination($passable)
        );

        return $pipeline($this->passable);
    }

    /**
     * 包装洋葱层级。
     */
    private function carry(): Closure
    {
        return function (callable $stack, mixed $pipe): Closure {
            return function (mixed $passable) use ($stack, $pipe): Response {
                if (is_callable($pipe)) {
                    return $pipe($passable, $stack);
                }

                if (is_string($pipe) && class_exists($pipe)) {
                    $pipe = new $pipe();
                }

                if ($pipe instanceof MiddlewareInterface) {
                    return $pipe->process($passable, $stack);
                }

                return $stack($passable);
            };
        };
    }
}
