<?php

namespace App\Models\Scopes;

use App\Support\SubjectContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Tự động giới hạn truy vấn theo môn đang hoạt động.
 *
 * Nhờ scope này, mọi model nghiệp vụ gắn với một môn sẽ không thể
 * rò rỉ dữ liệu sang môn khác, tránh lẫn lộn giữa các môn.
 */
class SubjectScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $subjectId = app(SubjectContext::class)->id();

        if ($subjectId === null) {
            return;
        }

        $builder->where($model->getTable().'.subject_id', $subjectId);
    }
}
