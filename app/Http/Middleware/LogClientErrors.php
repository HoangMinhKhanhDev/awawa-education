<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ghi lại các lỗi 4xx (405, 419, 429...) vào log riêng.
 *
 * Laravel không mặc định ghi HttpException 4xx nên khi người dùng thấy
 * "405 Method Not Allowed" mà log không có gì. Middleware này chỉ ghi, không
 * đổi response; sau khi xác định được URL gây lỗi thì xoá đi.
 */
class LogClientErrors
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if ($response->getStatusCode() >= 400 && $response->getStatusCode() < 500) {
            Log::channel('stack')->warning('client-error', [
                'status' => $response->getStatusCode(),
                'method' => $request->method(),
                'path' => $request->path(),
                'full_url' => $request->fullUrl(),
                'allow' => $response->headers->get('Allow'),
                'referer' => $request->header('Referer'),
                'user' => $request->user()?->id,
            ]);
        }

        return $response;
    }
}
