<?php

namespace Tests\Feature\Student;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Livewire\Dashboard;
use App\Models\AttemptAnswer;
use App\Models\Document;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\Question;
use App\Models\Subject;
use App\Models\TeamMembership;
use App\Models\User;
use App\Services\Assignments\AssignmentManager;
use App\Support\SubjectContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StudentDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Storage::fake('public');

        $this->subject = Subject::factory()->create();
        $this->student = User::factory()->student($this->subject)->create();

        TeamMembership::query()->create([
            'subject_id' => $this->subject->id,
            'student_id' => $this->student->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($this->student);
        app(SubjectContext::class)->set($this->subject->id);
    }

    private function publicDocument(): Document
    {
        return Document::factory()->create([
            'subject_id' => $this->subject->id,
            'is_public' => true,
            'file_path' => UploadedFile::fake()->create('tailieu.pdf')->store('documents', 'public'),
        ]);
    }

    public function test_overdue_assignment_becomes_the_hero_with_one_action(): void
    {
        $document = $this->publicDocument();
        app(AssignmentManager::class)->assign($document, dueAt: now()->subDay(), notify: false);

        Livewire::test(Dashboard::class)
            ->assertSee('Trễ hạn')
            ->assertSee('Mở ngay')
            ->assertSee($document->title);
    }

    public function test_in_progress_exam_shows_progress_and_time_left(): void
    {
        $exam = Exam::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => ExamType::Exam,
            'status' => ExamStatus::Published,
        ]);

        $first = Question::factory()->create(['subject_id' => $this->subject->id]);
        $second = Question::factory()->create(['subject_id' => $this->subject->id]);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $first->id, 'order' => 0]);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $second->id, 'order' => 1]);

        $attempt = ExamAttempt::factory()->create([
            'subject_id' => $this->subject->id,
            'exam_id' => $exam->id,
            'student_id' => $this->student->id,
            'status' => AttemptStatus::InProgress,
            'expires_at' => now()->addHour(),
        ]);

        AttemptAnswer::create(['attempt_id' => $attempt->id, 'question_id' => $first->id, 'answer_text' => 'x']);
        AttemptAnswer::create(['attempt_id' => $attempt->id, 'question_id' => $second->id]);

        Livewire::test(Dashboard::class)
            ->assertSee('Tiếp tục làm')
            ->assertSee($exam->title);
    }

    public function test_stats_strip_shows_ability_and_rank(): void
    {
        $exam = Exam::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => ExamType::Exam,
            'status' => ExamStatus::Published,
            'total_points' => 10,
        ]);

        ExamAttempt::factory()->graded()->create([
            'subject_id' => $this->subject->id,
            'exam_id' => $exam->id,
            'student_id' => $this->student->id,
            'score' => 8,
            'max_score' => 10,
        ]);

        Livewire::test(Dashboard::class)
            ->assertSee('Điểm thực lực')
            ->assertSee('Hạng trong đội')
            ->assertSee('Xem bảng xếp hạng');
    }

    public function test_empty_dashboard_shows_the_done_state(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSee('Xong hết rồi!')
            ->assertSee('Làm bài đầu tiên để có điểm thực lực');
    }

    public function test_document_search_filters_the_list(): void
    {
        Document::factory()->create([
            'subject_id' => $this->subject->id,
            'is_public' => true,
            'title' => 'Chuyên đề bất đẳng thức',
            'file_path' => 'documents/a.pdf',
        ]);
        Document::factory()->create([
            'subject_id' => $this->subject->id,
            'is_public' => true,
            'title' => 'Hình học phẳng',
            'file_path' => 'documents/b.pdf',
        ]);

        Livewire::test(Dashboard::class)
            ->set('docSearch', 'bất đẳng thức')
            ->assertSee('Chuyên đề bất đẳng thức')
            ->assertDontSee('Hình học phẳng');
    }
}
