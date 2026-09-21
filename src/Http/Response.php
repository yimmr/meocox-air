<?php

declare(strict_types=1);

namespace Meocox\Http;

use JsonSerializable;

/**
 * HTTP 响应对象。
 */
class Response
{
    /** @var callable|null */
    protected $streamCallback = null;

    public function __construct(
        protected string $content = '',
        protected int $statusCode = 200,
        protected array $headers = []
    ) {
    }

    /**
     * 快速构建 JSON 响应。
     */
    public static function json(mixed $data, int $status = 200, array $headers = []): static
    {
        $headers['Content-Type'] = 'application/json; charset=utf-8';
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new static($json !== false ? $json : '{}', $status, $headers);
    }

    /**
     * 快速构建 HTML 响应。
     */
    public static function html(string $html, int $status = 200, array $headers = []): static
    {
        $headers['Content-Type'] = 'text/html; charset=utf-8';

        return new static($html, $status, $headers);
    }

    /**
     * 快速构建纯文本响应。
     */
    public static function text(string $text, int $status = 200, array $headers = []): static
    {
        $headers['Content-Type'] = 'text/plain; charset=utf-8';

        return new static($text, $status, $headers);
    }

    /**
     * 快速构建 HTTP 重定向。
     */
    public static function redirect(string $url, int $status = 302, array $headers = []): static
    {
        $headers['Location'] = $url;

        return new static('', $status, $headers);
    }

    /**
     * 快速构建 204 No Content 响应。
     */
    public static function noContent(array $headers = []): static
    {
        return new static('', 204, $headers);
    }

    /**
     * 快速构建流式响应（常用于大文件下载或 Server-Sent Events）。
     */
    public static function stream(callable $callback, int $status = 200, array $headers = []): static
    {
        $response = new static('', $status, $headers);
        $response->streamCallback = $callback;

        return $response;
    }

    public function setStatusCode(int $code): static
    {
        $this->statusCode = $code;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setHeader(string $name, string|array $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * 追加响应头（若已存在则转换为数组形式保留多个同名头，如 Set-Cookie）。
     */
    public function addHeader(string $name, string $value): static
    {
        if (!isset($this->headers[$name])) {
            $this->headers[$name] = $value;
        } elseif (is_array($this->headers[$name])) {
            $this->headers[$name][] = $value;
        } else {
            $this->headers[$name] = [$this->headers[$name], $value];
        }

        return $this;
    }

    /**
     * 获取指定响应头。
     */
    public function getHeader(string $name, mixed $default = null): mixed
    {
        return $this->headers[$name] ?? $default;
    }

    /**
     * 声明追加 Set-Cookie 响应头。
     *
     * @param array{
     *     expires?: int|\DateTimeInterface,
     *     maxAge?: int,
     *     path?: string,
     *     domain?: string|null,
     *     secure?: bool,
     *     httpOnly?: bool,
     *     sameSite?: 'Lax'|'Strict'|'None'|null,
     * } $options Cookie 配置选项
     */
    public function withCookie(string $name, string $value, array $options = []): static
    {
        $cookie = urlencode($name) . '=' . urlencode($value);

        if (isset($options['expires'])) {
            $exp = $options['expires'];
            $timestamp = $exp instanceof \DateTimeInterface ? $exp->getTimestamp() : (int) $exp;
            $cookie .= '; Expires=' . gmdate('D, d M Y H:i:s T', $timestamp);
        }

        if (isset($options['maxAge'])) {
            $cookie .= '; Max-Age=' . (int) $options['maxAge'];
        }

        $path = $options['path'] ?? '/';
        $cookie .= '; Path=' . $path;

        if (!empty($options['domain'])) {
            $cookie .= '; Domain=' . $options['domain'];
        }

        if (!empty($options['secure'])) {
            $cookie .= '; Secure';
        }

        if ($options['httpOnly'] ?? true) {
            $cookie .= '; HttpOnly';
        }

        if (!empty($options['sameSite'])) {
            $cookie .= '; SameSite=' . ucfirst(strtolower($options['sameSite']));
        }

        return $this->addHeader('Set-Cookie', $cookie);
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * 发送响应报文并输出内容。
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);

            foreach ($this->headers as $name => $values) {
                if (is_array($values)) {
                    $first = true;
                    foreach ($values as $val) {
                        header("{$name}: {$val}", $first ? true : false);
                        $first = false;
                    }
                } else {
                    header("{$name}: {$values}");
                }
            }
        }

        if ($this->streamCallback !== null) {
            ($this->streamCallback)();
            return;
        }

        echo $this->content;
    }
}
