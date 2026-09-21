<?php

declare(strict_types=1);

namespace Meocox\Http;

/**
 * HTTP 中间件标准契约。
 */
interface MiddlewareInterface
{
    /**
     * 处理传入的 HTTP 请求并返回响应。
     *
     * @param Request $request
     * @param callable(Request): Response $next
     * @return Response
     */
    public function process(Request $request, callable $next): Response;
}
