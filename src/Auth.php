<?php

declare(strict_types=1);

namespace Meocox;

use Meocox\Http\Request;

/**
 * 宿主无关的声明式鉴权上下文门面。
 * 遵循现代 Data Access Layer (DAL) 理念，由宿主环境注册提取逻辑，Air 内部 100% 保持解耦纯净。
 */
final class Auth
{
    /** @var callable|null */
    private static $resolver = null;
    private static mixed $currentUser = null;
    private static bool $userResolved = false;

    /**
     * 注册鉴权解析器（如从 Session、JWT 或 WordPress wp_get_current_user 解析当前用户）。
     *
     * @param callable(Request): mixed $resolver
     */
    public static function resolver(callable $resolver): void
    {
        self::$resolver = $resolver;
        self::$userResolved = false;
        self::$currentUser = null;
    }

    /**
     * 手动注入当前已登录用户对象（常用于测试或手动登录后设置状态）。
     */
    public static function login(mixed $user): void
    {
        self::$currentUser = $user;
        self::$userResolved = true;
    }

    /**
     * 注销当前用户上下文。
     */
    public static function logout(): void
    {
        self::$currentUser = null;
        self::$userResolved = true;
    }

    /**
     * 获取当前登录用户。
     */
    public static function user(?Request $request = null): mixed
    {
        if (self::$userResolved) {
            return self::$currentUser;
        }

        if (self::$resolver !== null) {
            $req = $request ?? Air::request();
            self::$currentUser = (self::$resolver)($req);
            self::$userResolved = true;
            return self::$currentUser;
        }

        return null;
    }

    /**
     * 获取当前登录用户唯一标识 ID。
     */
    public static function id(?Request $request = null): string|int|null
    {
        $user = self::user($request);

        if ($user === null) {
            return null;
        }

        if (is_array($user)) {
            return $user['id'] ?? $user['ID'] ?? null;
        }

        if (is_object($user)) {
            return $user->id ?? $user->ID ?? (method_exists($user, 'getId') ? $user->getId() : null);
        }

        return is_scalar($user) ? $user : null;
    }

    /**
     * 检查用户是否已登录。
     */
    public static function check(?Request $request = null): bool
    {
        return self::user($request) !== null && self::id($request) !== null;
    }

    /**
     * 检查当前是否为访客（未登录）。
     */
    public static function guest(?Request $request = null): bool
    {
        return !self::check($request);
    }

    /**
     * 重置状态（测试隔离）。
     */
    public static function reset(): void
    {
        self::$resolver = null;
        self::$currentUser = null;
        self::$userResolved = false;
    }
}
