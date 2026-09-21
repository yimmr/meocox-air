<?php

declare(strict_types=1);

namespace Meocox\Utils;

use InvalidArgumentException;

/**
 * 零外部依赖的轻量原生 JWT (JSON Web Token) 工具类。
 */
final class JWT
{
    /** @var array<string, string> 支持的对称 HMAC 算法映射 */
    private const ALGO_MAP = [
        'HS256' => 'sha256',
        'HS384' => 'sha384',
        'HS512' => 'sha512',
    ];

    /**
     * 将有效载荷编码为 JWT 令牌字符串。
     *
     * @param array<string, mixed> $payload 载荷数据
     * @param string $secret 签名密钥
     * @param string $algo 签名算法 (默认 HS256)
     */
    public static function encode(array $payload, string $secret, string $algo = 'HS256'): string
    {
        $algoUpper = strtoupper($algo);
        if (!isset(self::ALGO_MAP[$algoUpper])) {
            throw new InvalidArgumentException("Unsupported JWT algorithm: [{$algo}].");
        }

        $header = ['typ' => 'JWT', 'alg' => $algoUpper];
        $headerJson = (string) json_encode($header, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $payloadJson = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $headerEncoded = self::base64UrlEncode($headerJson);
        $payloadEncoded = self::base64UrlEncode($payloadJson);

        $signature = self::sign("{$headerEncoded}.{$payloadEncoded}", $secret, $algoUpper);

        return "{$headerEncoded}.{$payloadEncoded}.{$signature}";
    }

    /**
     * 解析并验证 JWT 令牌，若验证成功返回载荷数组，失败或过期返回 null。
     *
     * @param string $token 待验证的 JWT 字符串
     * @param string $secret 验证密钥
     * @param list<string> $allowedAlgos 允许的算法白名单
     * @param int $leeway 允许的时间偏差（秒）
     * @return array<string, mixed>|null
     */
    public static function decode(
        string $token,
        string $secret,
        array $allowedAlgos = ['HS256'],
        int $leeway = 0
    ): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signature] = $parts;

        $headerJson = self::base64UrlDecode($headerB64);
        $header = json_decode($headerJson, true);
        if (!is_array($header) || empty($header['alg']) || !is_string($header['alg'])) {
            return null;
        }

        $algo = strtoupper($header['alg']);
        $allowedUpper = array_map('strtoupper', $allowedAlgos);
        if (!in_array($algo, $allowedUpper, true) || !isset(self::ALGO_MAP[$algo])) {
            return null;
        }

        // 验证签名（使用时序攻击安全的 hash_equals）
        $expectedSignature = self::sign("{$headerB64}.{$payloadB64}", $secret, $algo);
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $payloadJson = self::base64UrlDecode($payloadB64);
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return null;
        }

        $currentTime = time();

        // 校验生效时间 (nbf - Not Before)
        if (isset($payload['nbf']) && is_numeric($payload['nbf'])) {
            if ($currentTime + $leeway < (int) $payload['nbf']) {
                return null;
            }
        }

        // 校验过期时间 (exp - Expiration Time)
        if (isset($payload['exp']) && is_numeric($payload['exp'])) {
            if ($currentTime - $leeway >= (int) $payload['exp']) {
                return null;
            }
        }

        return $payload;
    }

    /**
     * 生成基于 HMAC 的签名。
     */
    public static function sign(string $data, string $secret, string $algo = 'HS256'): string
    {
        $algoUpper = strtoupper($algo);
        $hashAlgo = self::ALGO_MAP[$algoUpper] ?? throw new InvalidArgumentException("Unsupported algorithm: [{$algo}].");
        $rawHash = hash_hmac($hashAlgo, $data, $secret, true);

        return self::base64UrlEncode($rawHash);
    }

    /**
     * URL 安全的 Base64 编码。
     */
    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * URL 安全的 Base64 解码。
     */
    public static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return $decoded !== false ? $decoded : '';
    }
}
