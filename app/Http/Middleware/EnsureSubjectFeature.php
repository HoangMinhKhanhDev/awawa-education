<?php

namespace App\Http\Middleware;

use App\Enums\SubjectFeature;
use App\Support\SubjectContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chặn truy cập nếu môn của người dùng không bật tính năng tương ứng.
 *
 * Ví dụ: ->middleware('feature:exams')
 */
class EnsureSubjectFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $subject = app(SubjectContext::class)->subject();

        if ($subject === null || ! $subject->hasFeature(SubjectFeature::from($feature))) {
            abort(403, 'Tính năng này chưa được bật cho môn của bạn.');
        }

        return $next($request);
    }
}
