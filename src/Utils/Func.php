<?php

declare(strict_types=1);

namespace Meocox\Utils;

use Throwable;

/**
 * 常用函数执行辅助工具库。
 */
final class Func
{
    /**
     * 包装具有容错需求的闭包执行，支持失败重试、指数退避延时与条件重试拦截。
     *
     * @template T
     * @param callable(): T $callback 待执行的业务闭包
     * @param int|array{
     *     retries?: int,
     *     delay?: int,
     *     backoff?: float,
     *     retryIf?: callable(Throwable, int): bool,
     *     onRetry?: callable(Throwable, int, int): void,
     *     onFinalError?: callable(Throwable): mixed,
     * } $options 重试配置选项或重试次数
     * @return T
     * @throws Throwable 当重试耗尽且未设置 onFinalError 时抛出最后一次捕获的异常
     */
    public static function retry(callable $callback, int|array $options = 3): mixed
    {
        if (is_int($options)) {
            $options = ['retries' => $options];
        }

        $retries = max(1, (int) ($options['retries'] ?? 3));
        $baseDelay = max(0, (int) ($options['delay'] ?? 0));
        $backoff = max(1.0, (float) ($options['backoff'] ?? 1.0));
        $retryIf = $options['retryIf'] ?? null;
        $onRetry = $options['onRetry'] ?? null;
        $onFinalError = $options['onFinalError'] ?? null;

        $attempt = 0;
        $lastError = null;

        while ($attempt < $retries) {
            $attempt++;

            try {
                return $callback();
            } catch (Throwable $e) {
                $lastError = $e;

                // 达到最大重试次数，直接跳出
                if ($attempt >= $retries) {
                    break;
                }

                // 校验重试条件断言（若指定了 retryIf 且返回 false 则中断重试）
                if (is_callable($retryIf) && !$retryIf($e, $attempt)) {
                    break;
                }

                // 计算当前重试延迟（毫秒）
                $currentDelay = $baseDelay > 0
                    ? (int) round($baseDelay * ($backoff ** ($attempt - 1)))
                    : 0;

                if (is_callable($onRetry)) {
                    $onRetry($e, $attempt, $currentDelay);
                }

                if ($currentDelay > 0) {
                    usleep($currentDelay * 1000);
                }
            }
        }

        if (is_callable($onFinalError) && $lastError !== null) {
            return $onFinalError($lastError);
        }

        if ($lastError !== null) {
            throw $lastError;
        }

        return null;
    }
}
