<?php

namespace App\Livewire\Admin;

use App\Enums\AttemptStatus;
use App\Enums\ExamType;
use App\Enums\Role;
use App\Models\AiUsageLog;
use App\Models\ApiKey;
use App\Models\Document;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\KnowledgeMap;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Support\SafeCache;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Thống kê')]
class Stats extends Component
{
    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
    }

    public function render(): View
    {
        // 16 COUNT + 2 series mỗi lần mở trang: cache 5 phút, admin không cần realtime.
        // Tất cả cache ở đây lưu mảng thô, không lưu Collection: giá trị cache
        // phải sống sót qua các lần deploy. Xem `App\Support\SafeCache`.
        $counts = SafeCache::remember('admin-stats:v2:counts', 300, fn (): array => [
            'teacher' => User::query()->where('role', Role::Teacher->value)->count(),
            'student' => User::query()->where('role', Role::Student->value)->count(),
            'subject' => Subject::query()->count(),
            'question' => Question::query()->count(),
            'exam' => Exam::query()->ofType(ExamType::Exam)->count(),
            'assignment' => Exam::query()->ofType(ExamType::Assignment)->count(),
            'attempt' => ExamAttempt::query()->count(),
            'graded' => ExamAttempt::query()->where('status', AttemptStatus::Graded->value)->count(),
            'document' => Document::query()->count(),
            'map' => KnowledgeMap::query()->count(),
            'api_key' => ApiKey::query()->count(),
            'api_key_active' => ApiKey::query()->usable()->count(),
            'ai_calls' => AiUsageLog::query()->where('is_success', true)->count(),
            'ai_errors' => AiUsageLog::query()->where('is_success', false)->count(),
            'ai_tokens' => (int) AiUsageLog::query()->sum('total_tokens'),
            'ai_today' => AiUsageLog::query()->whereDate('created_at', today())->count(),
        ]);

        return view('livewire.admin.stats', [
            'counts' => $counts,
            'attemptSeries' => SafeCache::remember('admin-stats:v2:attempt-series', 300, fn (): array => $this->dailySeries(ExamAttempt::query())),
            'aiSeries' => SafeCache::remember('admin-stats:v2:ai-series', 300, fn (): array => $this->dailySeries(AiUsageLog::query())),
            'subjectAverages' => SafeCache::remember('admin-stats:v2:subject-averages', 300, fn (): array => $this->subjectAverages()),
            'recentAiLogs' => AiUsageLog::query()->with(['user', 'subject'])->latest()->limit(8)->get(),
        ]);
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    protected function dailySeries(Builder $query): array
    {
        $rows = $query
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];

        for ($i = 13; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $key = $date->toDateString();
            $series[] = [
                'label' => $date->format('d/m'),
                'value' => (int) ($rows[$key] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @return array<int, array{name: string, average: float, attempts: int}>
     */
    protected function subjectAverages(): array
    {
        // Chỉ bài đã chấm xong, chuẩn hoá % để đề thang điểm khác nhau
        // vẫn so được với nhau.
        $rows = ExamAttempt::query()
            ->where('status', AttemptStatus::Graded->value)
            ->whereNotNull('score')
            ->select('subject_id', DB::raw('AVG(CASE WHEN max_score > 0 THEN score * 100.0 / max_score ELSE 0 END) as average'), DB::raw('COUNT(*) as attempts'))
            ->groupBy('subject_id')
            ->get();

        $subjects = Subject::query()->pluck('name', 'id');

        return $rows
            ->map(fn ($row) => [
                'name' => (string) ($subjects[$row->subject_id] ?? 'Không rõ'),
                'average' => round((float) $row->average, 2),
                'attempts' => (int) $row->attempts,
            ])
            ->sortByDesc('average')
            ->values()
            ->all();
    }
}
