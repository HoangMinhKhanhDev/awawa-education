<?php

namespace App\Http\Middleware;

use App\Support\SubjectContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Xác định môn đang hoạt động cho request hiện tại.
 */
class SetSubjectContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(SubjectContext::class);
        $user = $request->user();

        if ($user !== null) {
            if ($user->isSuperAdmin()) {
                $context->set($request->session()->get('acting_subject_id'));
            } else {
                $context->set($user->subject_id);
            }
        }

        return $next($request);
    }
}
