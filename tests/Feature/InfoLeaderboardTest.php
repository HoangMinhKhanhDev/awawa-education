<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Livewire\Info;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Subject;
use App\Models\TeamMembership;
use App\Models\User;
use App\Support\SubjectContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InfoLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = Subject::factory()->create();
    }

    private function member(string $name): User
    {
        $student = User::factory()->student($this->subject)->create(['name' => $name]);

        TeamMembership::query()->create([
            'subject_id' => $this->subject->id,
            'student_id' => $student->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $student;
    }

    public function test_leaderboard_orders_members_by_best_percentage(): void
    {
        $exam = Exam::factory()->create(['subject_id' => $this->subject->id]);

        $high = $this->member('Hoc Sinh Cao');
        $low = $this->member('Hoc Sinh Thap');

        ExamAttempt::factory()->graded()->create([
            'subject_id' => $this->subject->id,
            'exam_id' => $exam->id,
            'student_id' => $high->id,
            'score' => 9,
            'max_score' => 10,
        ]);

        ExamAttempt::factory()->graded()->create([
            'subject_id' => $this->subject->id,
            'exam_id' => $exam->id,
            'student_id' => $low->id,
            'score' => 3,
            'max_score' => 10,
        ]);

        $viewer = $this->member('Triệu Thường Chung');

        $this->actingAs($viewer);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Info::class)
            ->assertSeeInOrder(['Hoc Sinh Cao', 'Hoc Sinh Thap'])
            ->assertSee('Bảng xếp hạng');
    }

    public function test_leaderboard_uses_best_percentage_not_sum_of_scores(): void
    {
        $exam = Exam::factory()->create(['subject_id' => $this->subject->id]);

        $consistent = $this->member('Hoc Sinh Deu');
        $busy = $this->member('Hoc Sinh LamNhieu');

        ExamAttempt::factory()->graded()->create([
            'subject_id' => $this->subject->id,
            'exam_id' => $exam->id,
            'student_id' => $consistent->id,
            'score' => 8,
            'max_score' => 10,
        ]);

        // Ba bài 3/10 có tổng 9 điểm nhưng tỉ lệ chỉ 30%.
        foreach ([1, 2, 3] as $attemptNo) {
            ExamAttempt::factory()->graded()->create([
                'subject_id' => $this->subject->id,
                'exam_id' => $exam->id,
                'student_id' => $busy->id,
                'attempt_no' => $attemptNo,
                'score' => 3,
                'max_score' => 10,
            ]);
        }

        $viewer = $this->member('Triệu Thường Chung');

        $this->actingAs($viewer);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Info::class)->assertSeeInOrder(['Hoc Sinh Deu', 'Hoc Sinh LamNhieu']);
    }

    public function test_leaderboard_ignores_attempts_that_are_not_finished(): void
    {
        $exam = Exam::factory()->create(['subject_id' => $this->subject->id]);

        $student = $this->member('Hoc Sinh Dang Lam');

        ExamAttempt::factory()->create([
            'subject_id' => $this->subject->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'status' => AttemptStatus::InProgress,
        ]);

        $viewer = $this->member('Triệu Thường Chung');

        $this->actingAs($viewer);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Info::class)
            ->assertSee('Hoc Sinh Dang Lam')
            ->assertSee('0 bài đã nộp');
    }
}
