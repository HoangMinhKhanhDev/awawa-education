<?php

namespace App\Livewire\Notebook;

use App\Models\AiUsageLog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Hoạt động AI')]
class Activity extends Component
{
    public string $purposeFilter = '';

    public string $statusFilter = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isTeacher(), 403);
    }

    public function render(): View
    {
        $logs = AiUsageLog::query()
            ->where('user_id', auth()->id())
            ->when($this->purposeFilter !== '', fn ($query) => $query->where('purpose', $this->purposeFilter))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('is_success', $this->statusFilter === 'success'))
            ->latest('id')
            ->limit(100)
            ->get();

        $summary = AiUsageLog::query()->where('user_id', auth()->id())->selectRaw(
            'COUNT(*) as calls, SUM(CASE WHEN is_success = 1 THEN 1 ELSE 0 END) as successes, SUM(total_tokens) as tokens',
        )->first();

        return view('livewire.notebook.activity', [
            'logs' => $logs,
            'summary' => $summary,
        ])->layout('components.layouts.app');
    }
}
