<?php

declare(strict_types=1);

namespace Meocox\Http;

use Meocox\Utils\Arr;

/**
 * 不可变 HTTP 请求对象，提供强类型数据获取与安全隔离。
 */
class Request
{
    protected ?array $jsonPayload = null;
    protected array $params = [];

    public function __construct(
        protected array $query = [],
        protected array $post = [],
        protected array $server = [],
        protected array $cookies = [],
        protected array $files = [],
        protected ?string $rawBody = null
    ) {
    }

    /**
     * 从 PHP 超全局变量中创建 Request 实例。
     */
    public static function createFromGlobals(): static
    {
        return new static(
            $_GET,
            $_POST,
            $_SERVER,
            $_COOKIE,
            $_FILES,
            file_get_contents('php://input') ?: null
        );
    }

    /**
     * createFromGlobals 的便捷别名 (对标现代框架惯例)。
     */
    public static function capture(): static
    {
        return static::createFromGlobals();
    }

    /**
     * 获取 HTTP 请求方法 (GET, POST, PUT, DELETE 等)。
     */
    public function method(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST') {
            $override = $this->header('X-HTTP-Method-Override')
                ?? ($this->post['_method'] ?? null)
                ?? ($this->query['_method'] ?? null);
            if ($override && is_string($override)) {
                return strtoupper($override);
            }
        }

        return $method;
    }

    /**
     * 获取完整的请求 URI。
     */
    public function uri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    /**
     * 获取规范化请求路径 (不含 Query 字符串)。
     */
    public function path(): string
    {
        $uri = $this->uri();
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        return '/' . trim($path, '/');
    }

    /**
     * 获取客户端真实 IP。
     */
    public function ip(): string
    {
        return $this->header('X-Forwarded-For')
            ?? $this->header('X-Real-IP')
            ?? $this->server['REMOTE_ADDR']
            ?? '127.0.0.1';
    }

    /**
     * 获取指定请求头。
     */
    public function header(string $key, ?string $default = null): ?string
    {
        $normalizedKey = strtoupper(str_replace('-', '_', $key));

        if (isset($this->server["HTTP_{$normalizedKey}"])) {
            return $this->server["HTTP_{$normalizedKey}"];
        }

        if (isset($this->server[$normalizedKey])) {
            return $this->server[$normalizedKey];
        }

        return $default;
    }

    /**
     * 获取全部请求头。
     */
    public function headers(): array
    {
        $headers = [];
        foreach ($this->server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$headerName] = (string) $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
                $headers[$headerName] = (string) $value;
            }
        }

        return $headers;
    }

    /**
     * 获取 Bearer Token。
     */
    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization') ?? $this->server['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        if ($auth && preg_match('/^Bearer\s+(.+)$/i', $auth, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * 获取 URL 查询参数 ($_GET)。
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return Arr::get($this->query, $key, $default);
    }

    /**
     * 获取表单提交参数 ($_POST)。
     */
    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->post;
        }

        return Arr::get($this->post, $key, $default);
    }

    /**
     * 获取 JSON Payload 请求体数据。
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($this->jsonPayload === null) {
            if ($this->rawBody !== null && $this->rawBody !== '') {
                $decoded = json_decode($this->rawBody, true);
                $this->jsonPayload = is_array($decoded) ? $decoded : [];
            } else {
                $this->jsonPayload = [];
            }
        }

        if ($key === null) {
            return $this->jsonPayload;
        }

        return Arr::get($this->jsonPayload, $key, $default);
    }

    /**
     * 统一按优先级获取请求参数：JSON -> POST -> 路由参数 -> GET。
     */
    public function input(string $key, mixed $default = null): mixed
    {
        if ($this->isJson() && Arr::has($this->json(), $key)) {
            return $this->json($key);
        }

        if (Arr::has($this->post, $key)) {
            return Arr::get($this->post, $key);
        }

        if (isset($this->params[$key])) {
            return $this->params[$key];
        }

        return Arr::get($this->query, $key, $default);
    }

    /**
     * 获取整数参数。
     */
    public function int(string $key, int $default = 0): int
    {
        $val = $this->input($key);
        return is_numeric($val) ? (int) $val : $default;
    }

    /**
     * 获取浮点数参数。
     */
    public function float(string $key, float $default = 0.0): float
    {
        $val = $this->input($key);
        return is_numeric($val) ? (float) $val : $default;
    }

    /**
     * 获取布尔参数。
     */
    public function bool(string $key, bool $default = false): bool
    {
        $val = $this->input($key);
        if ($val === null) {
            return $default;
        }

        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * 获取字符串参数。
     */
    public function string(string $key, string $default = ''): string
    {
        $val = $this->input($key);
        return is_scalar($val) ? (string) $val : $default;
    }

    /**
     * 获取合并后的全部输入数据。
     */
    public function all(): array
    {
        $all = $this->query;

        if ($this->isJson()) {
            $all = array_merge($all, $this->json());
        } else {
            $all = array_merge($all, $this->post);
        }

        return array_merge($all, $this->params);
    }

    /**
     * 仅获取指定键列表。
     */
    public function only(array $keys): array
    {
        return Arr::only($this->all(), $keys);
    }

    /**
     * 排除指定键列表。
     */
    public function except(array $keys): array
    {
        return Arr::except($this->all(), $keys);
    }

    /**
     * 设置动态路由匹配参数。
     */
    public function setParams(array $params): static
    {
        $this->params = $params;
        return $this;
    }

    /**
     * 获取动态路由参数。
     */
    public function param(string $key, ?string $default = null): ?string
    {
        return $this->params[$key] ?? $default;
    }

    /**
     * 获取全部动态路由参数。
     */
    public function params(): array
    {
        return $this->params;
    }

    /**
     * 获取上传文件信息。
     */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /**
     * 获取 Cookie。
     */
    public function cookie(string $key, ?string $default = null): ?string
    {
        return $this->cookies[$key] ?? $default;
    }

    /**
     * 是否是 JSON 格式请求。
     */
    public function isJson(): bool
    {
        $contentType = $this->header('Content-Type') ?? '';
        return str_contains($contentType, '/json') || str_contains($contentType, '+json');
    }

    /**
     * 是否是 AJAX 请求。
     */
    public function isAjax(): bool
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * 获取原生请求体。
     */
    public function rawBody(): ?string
    {
        return $this->rawBody;
    }
}
