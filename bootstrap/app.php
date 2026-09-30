<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureSubjectFeature;
use App\Http\Middleware\EnsureTeamMember;
use App\Http\Middleware\SecureHeaders;
use App\Http\Middleware\SetSubjectContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

/*
| Hostinger cấp sẵn biến môi trường DB cho website ở tầng máy chủ (đọc được qua
| getenv/$_SERVER) theo database cũ. Dotenv mặc định không ghi đè biến đã tồn tại
| nên Laravel sẽ lấy mật khẩu cũ và không kết nối được. Gỡ đúng các khoá DB mà
| máy chủ khai báo, để `.env` là nguồn chuẩn; các khoá khác giữ nguyên.
*/
foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DATABASE_URL'] as $key) {
    if (getenv($key) !== false) {
        putenv($key);
    }

    unset($_ENV[$key], $_SERVER[$key]);
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetSubjectContext::class,
            SecureHeaders::class,
        ]);

        $middleware->alias([
            'role' => EnsureRole::class,
            'team' => EnsureTeamMember::class,
            'feature' => EnsureSubjectFeature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
