<?php

namespace App\Livewire\Teacher;

use App\Enums\ExamType;
use App\Enums\SubjectFeature;
use App\Models\Document;
use App\Models\Exam;
use App\Models\Question;
use App\Models\TeamMembership;
use App\Support\SubjectContext;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Studio')]
class Studio extends Component
{
    public function render(): View
    {
        $subject = app(SubjectContext::class)->subject();

        $sections = [
            [
                'feature' => SubjectFeature::QuestionBank,
                'route' => 'studio.questions',
                'icon' => 'sparkles',
                'description' => 'Kho câu hỏi trắc nghiệm, tự luận, điền khuyết.',
                'count' => fn () => Question::query()->count(),
            ],
            [
                'feature' => SubjectFeature::Exams,
                'route' => 'studio.exams',
                'icon' => 'cap',
                'description' => 'Soạn đề thi từ ngân hàng câu hỏi và giao cho đội tuyển.',
                'count' => fn () => Exam::query()->ofType(ExamType::Exam)->count(),
            ],
            [
                'feature' => SubjectFeature::Assignments,
                'route' => 'studio.assignments',
                'icon' => 'check',
                'description' => 'Giao bài tập về nhà kèm hạn nộp.',
                'count' => fn () => Exam::query()->ofType(ExamType::Assignment)->count(),
            ],
            [
                'feature' => SubjectFeature::Documents,
                'route' => 'studio.documents',
                'icon' => 'mail',
                'description' => 'Đăng tài liệu học tập cho học sinh trong đội.',
                'count' => fn () => Document::query()->count(),
            ],
        ];

        return view('livewire.teacher.studio', [
            'subject' => $subject,
            'sections' => $sections,
            'memberCount' => TeamMembership::query()->where('status', 'active')->count(),
        ]);
    }
}
