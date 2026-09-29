<?php

namespace Tests\Feature\Teacher;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\MembershipStatus;
use App\Enums\QuestionType;
use App\Livewire\Teacher\ClassStats;
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

    public function test_class_stats_shows_distribution_and_exam_averages(): void
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

        Livewire::test(ClassStats::class)
            ->assertSee('Thống kê lớp')
            ->assertSee('Phân bố điểm thực lực')
            ->assertSee('Mức xem bài được giao')
            ->assertSee($exam->title)
            ->assertSee('Lượt nộp 14 ngày qua')
            ->assertSee('Xu hướng điểm lần đầu theo tuần')
            ->assertSee('Tốt nhất', escape: false);
    }

    public function test_class_stats_attributes_scores_to_the_right_students(): void
    {
        $exam = Exam::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => ExamType::Exam,
            'status' => ExamStatus::Published,
            'total_points' => 10,
        ]);

        // Giỏi 90%, yếu 30%: phân bố phải đếm đúng từng người.
        ExamAttempt::factory()->graded()->create([
            'subject_id' => $this->subject->id,
            'exam_id' => $exam->id,
            'student_id' => $this->student->id,
            'score' => 9,
            'max_score' => 10,
        ]);

        $weak = User::factory()->student($this->subject)->create(['name' => 'Hoc Sinh Yeu']);
        TeamMembership::query()->create([
            'subject_id' => $this->subject->id,
            'student_id' => $weak->id,
            'status' => MembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        ExamAttempt::factory()->graded()->create([
            'subject_id' => $this->subject->id,
            'exam_id' => $exam->id,
            'student_id' => $weak->id,
            'score' => 3,
            'max_score' => 10,
        ]);

        $component = Livewire::test(ClassStats::class);
        $distribution = $component->viewData('distribution');

        $this->assertSame(1, collect($distribution)->firstWhere('label', 'Giỏi (≥ 8)')['count']);
        $this->assertSame(1, collect($distribution)->firstWhere('label', 'Yếu (< 5)')['count']);

        // Bạn yếu (30%) lọt danh sách cần chú ý kèm link hồ sơ.
        $component->assertSee('Cần chú ý')
            ->assertSee('Hoc Sinh Yeu')
            ->assertSee(route('students.show', $weak), escape: false);
    }

    public function test_team_csv_downloads_with_vietnamese_headers(): void
    {
        $response = $this->get(route('students.export.csv'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->getContent();

        // BOM để Excel mở đúng tiếng Việt, phân cách chấm phẩy cho máy Việt.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Điểm thực lực (%)', $content);
        $this->assertStringContainsString($this->student->name, $content);
    }

    public function test_exam_grades_csv_downloads(): void
    {
        $exam = Exam::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => ExamType::Exam,
            'status' => ExamStatus::Published,
        ]);

        $response = $this->get(route('studio.grading.export.csv', $exam));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Họ tên', $response->getContent());
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
