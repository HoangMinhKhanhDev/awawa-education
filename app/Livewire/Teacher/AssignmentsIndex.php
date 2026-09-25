<?php

namespace App\Livewire\Teacher;

use App\Enums\ExamType;
use App\Enums\SubjectFeature;
use App\Livewire\Teacher\Concerns\ManagesAssessments;
use App\Models\Exam;
use App\Support\SubjectContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Bài tập')]
class AssignmentsIndex extends Component
{
    use ManagesAssessments, WithPagination;

    protected function assessmentType(): ExamType
    {
        return ExamType::Assignment;
    }

    public function mount(): void
    {
        Gate::authorize('viewAny', Exam::class);
    }

    public function render(): View
    {
        $subject = app(SubjectContext::class)->subject();

        return view('livewire.teacher.assessments-index', [
            'type' => ExamType::Assignment,
            'subject' => $subject,
            'exams' => $this->assessmentsQuery()->orderByDesc('created_at')->paginate(10),
            'featureEnabled' => $subject?->hasFeature(SubjectFeature::Assignments) ?? false,
        ]);
    }
}
