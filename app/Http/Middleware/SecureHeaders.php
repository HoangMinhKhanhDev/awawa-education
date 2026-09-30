<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header bảo mật cho mọi response.
 *
 * Không đặt `Content-Security-Policy` ở đây: Hostinger chèn sẵn CSP của hạ tầng
 * (`upgrade-insecure-requests`) ở tầng máy chủ nên header PHP này bị ghi đè và
 * tạo cảm giác an toàn giả. Muốn siết CSP thì tắt ở hPanel trước, rồi thêm
 * header ở đây. Còn lại 4 header dưới đang hoạt động bình thường.
 */
class SecureHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if ($request->isSecure() || ($request->header('X-Forwarded-Proto') === 'https')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
