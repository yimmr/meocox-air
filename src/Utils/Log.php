<?php

declare(strict_types=1);

namespace Meocox\Utils;

use Stringable;

/**
 * 结构化轻量文件日志记录器。
 */
final class Log
{
    private static ?string $logDirectory = null;
    private static string $channel = 'meocox';

    /**
     * 设置自定义日志存放目录。
     */
    public static function setDirectory(string $path): void
    {
        self::$logDirectory = rtrim($path, '/\\');
    }

    /**
     * 设置日志通道名。
     */
    public static function setChannel(string $channel): void
    {
        self::$channel = $channel;
    }

    public static function emergency(string|Stringable $message, array $context = []): void
    {
        self::write('EMERGENCY', $message, $context);
    }

    public static function alert(string|Stringable $message, array $context = []): void
    {
        self::write('ALERT', $message, $context);
    }

    public static function critical(string|Stringable $message, array $context = []): void
    {
        self::write('CRITICAL', $message, $context);
    }

    public static function error(string|Stringable $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function warning(string|Stringable $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function notice(string|Stringable $message, array $context = []): void
    {
        self::write('NOTICE', $message, $context);
    }

    public static function info(string|Stringable $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function debug(string|Stringable $message, array $context = []): void
    {
        self::write('DEBUG', $message, $context);
    }

    /**
     * 底层写入日志。
     */
    public static function write(string $level, string|Stringable $message, array $context = []): void
    {
        $dir = self::$logDirectory
            ?? (\Meocox\Config::has('paths.runtime')
                ? \Meocox\Config::get('paths.runtime') . '/logs'
                : dirname(__DIR__, 2) . '/runtime/logs');
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }

        $date = date('Y-m-d');
        $file = sprintf('%s/%s-%s.log', $dir, self::$channel, $date);

        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = self::interpolate((string) $message, $context);
        $contextJson = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';

        $record = sprintf("[%s] [%s.%s] %s%s\n", $timestamp, self::$channel, strtoupper($level), $formattedMessage, $contextJson);

        @file_put_contents($file, $record, FILE_APPEND | LOCK_EX);
    }

    /**
     * 替换占位符 {key}。
     */
    private static function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (is_scalar($val) || $val instanceof Stringable) {
                $replace['{' . $key . '}'] = (string) $val;
            }
        }

        return strtr($message, $replace);
    }
}
