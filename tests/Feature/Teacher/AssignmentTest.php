<?php

namespace Tests\Feature\Teacher;

use App\Enums\AssignableType;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\MapVisibility;
use App\Livewire\Dashboard;
use App\Livewire\Teacher\AssignmentsHub;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Document;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\KnowledgeMap;
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

class AssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private User $teacher;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->subject = Subject::factory()->create();
        $this->teacher = User::factory()->teacher($this->subject)->create();

        $this->student = User::factory()->student($this->subject)->create();
        TeamMembership::query()->create([
            'subject_id' => $this->subject->id,
            'student_id' => $this->student->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($this->teacher);
        app(SubjectContext::class)->set($this->subject->id);
    }

    private function publishedExam(array $attributes = []): Exam
    {
        return Exam::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => ExamType::Exam,
            'status' => ExamStatus::Published,
            ...$attributes,
        ]);
    }

    private function publicDocument(): Document
    {
        Storage::fake('public');

        return Document::factory()->create([
            'subject_id' => $this->subject->id,
            'is_public' => true,
            'file_path' => UploadedFile::fake()->create('tailieu.pdf')->store('documents', 'public'),
        ]);
    }

    public function test_assigning_a_document_creates_a_receipt_for_every_active_member(): void
    {
        $second = User::factory()->student($this->subject)->create();
        TeamMembership::query()->create([
            'subject_id' => $this->subject->id,
            'student_id' => $second->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        // Học sinh đã bị gỡ khỏi đội thì không nhận.
        $removed = User::factory()->student($this->subject)->create();
        TeamMembership::query()->create([
            'subject_id' => $this->subject->id,
            'student_id' => $removed->id,
            'status' => 'removed',
            'joined_at' => now(),
        ]);

        $document = $this->publicDocument();

        $assignment = app(AssignmentManager::class)->assign($document);

        $this->assertSame(2, $assignment->receipts()->count());
        $this->assertSame(
            1,
            $assignment->receipts()->where('user_id', $this->student->id)->whereNotNull('delivered_at')->count(),
        );
        $this->assertDatabaseHas('assignments', [
            'subject_id' => $this->subject->id,
            'assignable_type' => Document::class,
            'assignable_id' => $document->id,
        ]);
    }

    public function test_recalling_hides_it_from_students_but_keeps_the_data(): void
    {
        $document = $this->publicDocument();
        $assignment = app(AssignmentManager::class)->assign($document, notify: false);

        app(AssignmentManager::class)->recall($assignment, 'Thấy ra đề sai');

        $this->assertTrue($assignment->fresh()->isRecalled());
        $this->assertSame('Thấy ra đề sai', $assignment->fresh()->recall_reason);
        $this->assertDatabaseHas('documents', ['id' => $document->id]);
        $this->assertSame(1, $assignment->receipts()->count());
    }

    public function test_recalling_twice_is_a_no_op(): void
    {
        $manager = app(AssignmentManager::class);
        $assignment = $manager->assign($this->publicDocument(), notify: false);

        $manager->recall($assignment, notify: false);
        $first = $assignment->fresh()->recalled_at;

        $manager->recall($assignment, notify: false);

        $this->assertEquals($first, $assignment->fresh()->recalled_at);
    }

    public function test_reassigning_creates_a_new_row_and_keeps_the_history(): void
    {
        $manager = app(AssignmentManager::class);
        $document = $this->publicDocument();

        $first = $manager->assign($document, notify: false);
        $manager->recall($first, notify: false);

        $second = $manager->assign($document, notify: false);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, Assignment::query()->count());
        $this->assertTrue($first->fresh()->isRecalled());
        $this->assertFalse($second->isRecalled());
    }

    public function test_a_draft_exam_cannot_be_assigned(): void
    {
        $exam = Exam::factory()->create([
            'subject_id' => $this->subject->id,
            'status' => ExamStatus::Draft,
        ]);

        Livewire::test(AssignmentsHub::class)
            ->call('assign', 'exam', $exam->id)
            ->assertSet('error', 'Hãy giao đề cho đội trước, rồi mới giao cho học sinh.');

        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_a_private_document_cannot_be_assigned(): void
    {
        $document = Document::factory()->create([
            'subject_id' => $this->subject->id,
            'is_public' => false,
        ]);

        Livewire::test(AssignmentsHub::class)
            ->call('assign', 'document', $document->id)
            ->assertSet('error', 'Hãy chuyển tài liệu sang công khai trước khi giao cho học sinh.');

        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_content_cannot_be_assigned_when_its_feature_is_off(): void
    {
        $this->subject->features()->where('feature', 'documents')->update(['is_enabled' => false]);
        $this->subject->forgetFeatureCache();

        $document = $this->publicDocument();

        $this->assertFalse(AssignableType::Document->allowsFor($this->subject->fresh()));

        $this->expectExceptionMessage('Môn của bạn chưa bật tính năng Tài liệu.');

        app(AssignmentManager::class)->assign($document, notify: false);
    }

    public function test_student_can_open_and_complete_a_document_assignment(): void
    {
        $document = $this->publicDocument();
        app(AssignmentManager::class)->assign($document, notify: false);

        $this->actingAs($this->student);
        app(SubjectContext::class)->set($this->subject->id);

        $manager = app(AssignmentManager::class);

        $manager->markOpened($document, $this->student);
        $this->assertTrue($manager->activeFor($document, $this->student)?->receiptFor($this->student)?->isOpened());
        $this->assertFalse($manager->activeFor($document, $this->student)?->receiptFor($this->student)?->isCompleted());

        $manager->markCompleted($document, $this->student);
        $this->assertTrue($manager->activeFor($document, $this->student)?->receiptFor($this->student)?->isCompleted());
    }

    public function test_reopening_clears_the_completed_flag(): void
    {
        $document = $this->publicDocument();
        $manager = app(AssignmentManager::class);
        $manager->assign($document, notify: false);

        $manager->markCompleted($document, $this->student);
        $manager->markOpened($document, $this->student);

        $this->assertFalse($manager->activeFor($document, $this->student)?->receiptFor($this->student)?->isCompleted());
    }

    public function test_recalled_assignment_disappears_from_the_student_dashboard(): void
    {
        $document = $this->publicDocument();
        $manager = app(AssignmentManager::class);
        $assignment = $manager->assign($document, notify: false);

        $this->actingAs($this->student);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Dashboard::class)
            ->assertSee('Được giao, chưa xem xong')
            ->assertSee($document->title);

        $manager->recall($assignment, notify: false);

        // Tên tài liệu vẫn có ở mục "Tài liệu công khai", nên kiểm tra trực tiếp
        // danh sách được giao thay vì tìm chuỗi trên toàn trang.
        $component = Livewire::test(Dashboard::class)->assertDontSee('Được giao, chưa xem xong');

        $this->assertCount(0, $component->viewData('studentData')['assignments']);
    }

    public function test_completed_assignment_leaves_the_student_dashboard(): void
    {
        $document = $this->publicDocument();
        app(AssignmentManager::class)->assign($document, notify: false);

        $this->actingAs($this->student);
        app(SubjectContext::class)->set($this->subject->id);

        $before = Livewire::test(Dashboard::class);
        $this->assertCount(1, $before->viewData('studentData')['assignments']);

        $after = Livewire::test(Dashboard::class)
            ->call('markAssignmentDone', Assignment::query()->firstOrFail()->id)
            ->assertSee('Đã ghi nhận bạn đã xem xong');

        $this->assertCount(0, $after->viewData('studentData')['assignments']);

        // Đã xong thì hết mục "chưa xem xong".
        Livewire::test(Dashboard::class)->assertDontSee('Được giao, chưa xem xong');
    }

    public function test_student_cannot_open_or_complete_someone_elses_assignment(): void
    {
        $other = User::factory()->student($this->subject)->create();
        TeamMembership::query()->create([
            'subject_id' => $this->subject->id,
            'student_id' => $other->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $document = $this->publicDocument();
        $assignment = app(AssignmentManager::class)->assign($document, notify: false);

        // Xoá receipt của học sinh đang đăng nhập để mô phỏng việc cố thao tác
        // lên lần giao của người khác.
        $assignment->receipts()->where('user_id', $this->student->id)->delete();

        $this->actingAs($this->student);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Dashboard::class)
            ->call('markAssignmentDone', $assignment->id)
            ->assertDontSee('Đã ghi nhận bạn đã xem xong');

        $this->assertNull($assignment->receipts()->where('user_id', $this->student->id)->first());
    }

    public function test_exam_completion_is_synced_from_the_attempt(): void
    {
        $exam = $this->publishedExam();
        $manager = app(AssignmentManager::class);
        $manager->assign($exam, notify: false);

        $this->assertFalse($manager->activeFor($exam, $this->student)?->receiptFor($this->student)?->isCompleted());

        ExamAttempt::factory()->graded()->create([
            'subject_id' => $this->subject->id,
            'exam_id' => $exam->id,
            'student_id' => $this->student->id,
        ]);

        $manager->syncExamProgress($exam);

        $this->assertTrue($manager->activeFor($exam, $this->student)?->receiptFor($this->student)?->isCompleted());
    }

    public function test_progress_counts_are_reported_correctly(): void
    {
        $document = $this->publicDocument();
        $manager = app(AssignmentManager::class);
        $assignment = $manager->assign($document, notify: false);

        $this->assertSame(1, $assignment->progress()['total']);
        $this->assertSame(0, $assignment->progress()['opened']);
        $this->assertSame(0, $assignment->completionPercent());

        $manager->markCompleted($document, $this->student);

        $progress = $assignment->fresh()->progress();

        $this->assertSame(1, $progress['opened']);
        $this->assertSame(1, $progress['completed']);
        $this->assertSame(100, $assignment->fresh()->completionPercent());
    }

    public function test_hub_lists_open_assignments_and_hides_assigned_content_from_the_picker(): void
    {
        $exam = $this->publishedExam(['title' => 'Đề giữa kỳ']);
        $document = $this->publicDocument();

        app(AssignmentManager::class)->assign($exam, notify: false);

        $component = Livewire::test(AssignmentsHub::class)
            ->assertSee('Đề giữa kỳ')
            ->assertSee($document->title);

        // Nội dung đã giao thì không còn trong danh sách chọn.
        $keys = collect($component->viewData('assignable'))
            ->map(fn (array $option): string => $option['model']::class.'-'.$option['model']->getKey())
            ->all();

        $this->assertNotContains(Exam::class.'-'.$exam->id, $keys);
        $this->assertContains(Document::class.'-'.$document->id, $keys);
    }

    public function test_announcement_and_map_can_be_assigned_too(): void
    {
        $announcement = Announcement::factory()->create([
            'subject_id' => $this->subject->id,
            'published_at' => now()->subMinute(),
        ]);

        $map = KnowledgeMap::factory()->create([
            'subject_id' => $this->subject->id,
            'visibility' => MapVisibility::Subject,
        ]);

        $manager = app(AssignmentManager::class);

        $this->assertSame(
            AssignableType::Announcement,
            $manager->assign($announcement, notify: false)->type(),
        );

        $this->assertSame(
            AssignableType::KnowledgeMap,
            $manager->assign($map, notify: false)->type(),
        );
    }

    public function test_teacher_cannot_assign_content_from_another_subject(): void
    {
        $other = Subject::factory()->create();
        $document = Document::factory()->create(['subject_id' => $other->id, 'is_public' => true]);

        Livewire::test(AssignmentsHub::class)
            ->call('assign', 'document', $document->id)
            ->assertSet('error', 'Không tìm thấy nội dung cần giao.');

        $this->assertDatabaseCount('assignments', 0);
    }
}
