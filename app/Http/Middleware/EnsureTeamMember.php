<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Yêu cầu học sinh đã là thành viên chính thức của đội tuyển môn.
 */
class EnsureTeamMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        if ($user->isSuperAdmin() || $user->isTeacher()) {
            return $next($request);
        }

        if (! $user->isActiveMemberOf($user->subject_id)) {
            abort(403, 'Bạn cần được giáo viên thêm vào đội tuyển để sử dụng tính năng này.');
        }

        return $next($request);
    }
}
