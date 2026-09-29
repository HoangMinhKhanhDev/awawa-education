<?php

namespace Tests\Feature\Teacher;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\MembershipStatus;
use App\Enums\QuestionType;
use App\Livewire\Teacher\StudentShow;
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

class StudentShowTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private User $teacher;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Storage::fake('public');

        $this->subject = Subject::factory()->create();
        $this->teacher = User::factory()->teacher($this->subject)->create();
        $this->student = User::factory()->student($this->subject)->create();

        TeamMembership::query()->create([
            'subject_id' => $this->subject->id,
            'student_id' => $this->student->id,
            'status' => MembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($this->teacher);
        app(SubjectContext::class)->set($this->subject->id);
    }

    public function test_teacher_sees_scores_attempts_and_assignments(): void
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

        $document = Document::factory()->create([
            'subject_id' => $this->subject->id,
            'is_public' => true,
            'file_path' => UploadedFile::fake()->create('tailieu.pdf')->store('documents', 'public'),
        ]);
        app(AssignmentManager::class)->assign($document, notify: false);

        Livewire::test(StudentShow::class, ['student' => $this->student->id])
            ->assertSee($this->student->name)
            ->assertSee('Điểm thực lực')
            ->assertSee($exam->title)
            ->assertSee($document->title)
            ->assertSee('Các lần nộp gần đây');
    }

    public function test_teacher_of_another_subject_gets_404(): void
    {
        $otherSubject = Subject::factory()->create();
        $otherTeacher = User::factory()->teacher($otherSubject)->create();

        $this->actingAs($otherTeacher);
        app(SubjectContext::class)->set($otherSubject->id);

        Livewire::test(StudentShow::class, ['student' => $this->student->id])
            ->assertNotFound();
    }

    public function test_team_export_downloads_xlsx_matching_the_screen(): void
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

        $response = $this->get(route('students.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $texts = $this->xlsxTexts($response->getContent());

        $this->assertContains($this->student->name, $texts);
        $this->assertContains('Điểm thực lực (%)', $texts);
    }

    public function test_exam_grades_export_lists_every_member_with_per_question_points(): void
    {
        $exam = Exam::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => ExamType::Exam,
            'status' => ExamStatus::Published,
            'total_points' => 10,
        ]);

        $question = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => QuestionType::FillBlank,
            'points' => 10,
            'answer' => 'Hà Nội',
        ]);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $question->id, 'order' => 0, 'points' => 10]);

        $attempt = ExamAttempt::factory()->graded()->create([
            'subject_id' => $this->subject->id,
            'exam_id' => $exam->id,
            'student_id' => $this->student->id,
            'score' => 10,
            'max_score' => 10,
        ]);
        AttemptAnswer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'answer_text' => 'Hà Nội',
            'awarded_points' => 10,
            'is_correct' => true,
        ]);

        $response = $this->get(route('studio.grading.export', $exam));

        $response->assertOk();

        $texts = $this->xlsxTexts($response->getContent());

        $this->assertContains($this->student->name, $texts);
        $this->assertContains('Câu 1', $texts);
    }

    public function test_exam_grades_export_is_forbidden_for_other_subjects(): void
    {
        $exam = Exam::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => ExamType::Exam,
            'status' => ExamStatus::Published,
        ]);

        $otherSubject = Subject::factory()->create();
        $otherTeacher = User::factory()->teacher($otherSubject)->create();

        $this->actingAs($otherTeacher);
        app(SubjectContext::class)->set($otherSubject->id);

        // Scope môn lọc nên đề như không tồn tại với giáo viên môn khác.
        $this->get(route('studio.grading.export', $exam))->assertNotFound();
    }

    /**
     * @return array<int, string>
     */
    protected function xlsxTexts(string $binary): array
    {
        $path = tempnam(sys_get_temp_dir(), 'test-xlsx');
        file_put_contents($path, $binary);

        $zip = new \ZipArchive;
        $zip->open($path);

        $shared = $zip->getFromName('xl/sharedStrings.xml') ?: '';
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml') ?: '';
        $zip->close();
        @unlink($path);

        preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $shared, $matches);

        $strings = array_map('html_entity_decode', $matches[1]);

        $texts = $strings;

        // Số trong sheet để nguyên; chỉ cần chuỗi cho assert nội dung.
        preg_match_all('/<v>([\d.]+)<\/v>/', $sheet, $numbers);
        $texts = array_merge($texts, $numbers[1]);

        return $texts;
    }
}
