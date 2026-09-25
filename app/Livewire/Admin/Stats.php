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
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Thống kê')]
class Stats extends Component
{
    public function render(): View
    {
        return view('livewire.admin.stats', [
            'counts' => [
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
            ],
            'attemptSeries' => $this->dailySeries(ExamAttempt::query()),
            'aiSeries' => $this->dailySeries(AiUsageLog::query()),
            'subjectAverages' => $this->subjectAverages(),
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
     * @return Collection<int, array{name: string, average: float, attempts: int}>
     */
    protected function subjectAverages(): Collection
    {
        $rows = ExamAttempt::query()
            ->whereNotNull('score')
            ->select('subject_id', DB::raw('AVG(score) as average'), DB::raw('COUNT(*) as attempts'))
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
            ->values();
    }
}
