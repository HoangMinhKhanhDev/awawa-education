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

        $waiting = $this->member('Hoc Sinh Cho Cham');

        ExamAttempt::factory()->submitted()->create([
            'subject_id' => $this->subject->id,
            'exam_id' => $exam->id,
            'student_id' => $waiting->id,
            'score' => 10,
            'max_score' => 10,
        ]);

        $viewer = $this->member('Triệu Thường Chung');

        $this->actingAs($viewer);
        app(SubjectContext::class)->set($this->subject->id);

        // Bài đang làm dở và bài chờ chấm tay đều chưa vào bảng.
        Livewire::test(Info::class)
            ->assertSee('Hoc Sinh Dang Lam')
            ->assertSee('Hoc Sinh Cho Cham')
            ->assertSee('0 đề · 0 lượt làm');
    }

    public function test_leaderboard_averages_best_per_exam_instead_of_global_best(): void
    {
        $easy = Exam::factory()->create(['subject_id' => $this->subject->id]);
        $hard = Exam::factory()->create(['subject_id' => $this->subject->id]);

        $farmer = $this->member('Hoc Sinh Cay De');
        $steady = $this->member('Hoc Sinh Vung');

        // Cày đề dễ 2 lần đều 100%, đề khó chỉ 60%: trung bình 80.
        foreach ([1, 2] as $attemptNo) {
            ExamAttempt::factory()->graded()->create([
                'subject_id' => $this->subject->id,
                'exam_id' => $easy->id,
                'student_id' => $farmer->id,
                'attempt_no' => $attemptNo,
                'score' => 10,
                'max_score' => 10,
            ]);
        }

        ExamAttempt::factory()->graded()->create([
            'subject_id' => $this->subject->id,
            'exam_id' => $hard->id,
            'student_id' => $farmer->id,
            'score' => 6,
            'max_score' => 10,
        ]);

        // Chỉ làm đề khó một lần 90%: trung bình 90, xếp trên dù ít đề hơn.
        ExamAttempt::factory()->graded()->create([
            'subject_id' => $this->subject->id,
            'exam_id' => $hard->id,
            'student_id' => $steady->id,
            'score' => 9,
            'max_score' => 10,
        ]);

        $viewer = $this->member('Triệu Thường Chung');

        $this->actingAs($viewer);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Info::class)->assertSeeInOrder(['Hoc Sinh Vung', 'Hoc Sinh Cay De']);
    }

    public function test_leaderboard_prefers_more_exams_on_equal_average(): void
    {
        $first = Exam::factory()->create(['subject_id' => $this->subject->id]);
        $second = Exam::factory()->create(['subject_id' => $this->subject->id]);

        $wide = $this->member('Hoc Sinh Rong');
        $narrow = $this->member('Hoc Sinh Hep');

        foreach ([$first, $second] as $exam) {
            ExamAttempt::factory()->graded()->create([
                'subject_id' => $this->subject->id,
                'exam_id' => $exam->id,
                'student_id' => $wide->id,
                'score' => 8,
                'max_score' => 10,
            ]);
        }

        ExamAttempt::factory()->graded()->create([
            'subject_id' => $this->subject->id,
            'exam_id' => $first->id,
            'student_id' => $narrow->id,
            'score' => 8,
            'max_score' => 10,
        ]);

        $viewer = $this->member('Triệu Thường Chung');

        $this->actingAs($viewer);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Info::class)->assertSeeInOrder(['Hoc Sinh Rong', 'Hoc Sinh Hep']);
    }

    private function averageOf(string $studentName): float
    {
        $viewer = $this->member('Triệu Thường Chung');

        $this->actingAs($viewer);
        app(SubjectContext::class)->set($this->subject->id);

        $rows = Livewire::test(Info::class)->viewData('leaderboard');

        return (float) $rows->firstWhere(fn (array $row): bool => $row['student']?->name === $studentName)['average'];
    }

    public function test_retakes_do_not_move_the_ranking(): void
    {
        $exam = Exam::factory()->create(['subject_id' => $this->subject->id]);

        $farmer = $this->member('Hoc Sinh Cay Lai');
        $steady = $this->member('Hoc Sinh On Dinh');

        // Cày lại 100% cũng vô ích vì chỉ tính lần đầu 60%.
        foreach ([[1, 6], [2, 10]] as [$attemptNo, $score]) {
            ExamAttempt::factory()->graded()->create([
                'subject_id' => $this->subject->id,
                'exam_id' => $exam->id,
                'student_id' => $farmer->id,
                'attempt_no' => $attemptNo,
                'score' => $score,
                'max_score' => 10,
            ]);
        }

        ExamAttempt::factory()->graded()->create([
            'subject_id' => $this->subject->id,
            'exam_id' => $exam->id,
            'student_id' => $steady->id,
            'score' => 7,
            'max_score' => 10,
        ]);

        $this->assertSame(60.0, $this->averageOf('Hoc Sinh Cay Lai'));
        $this->assertSame(70.0, $this->averageOf('Hoc Sinh On Dinh'));
    }

    public function test_exam_weight_shifts_the_ability_average(): void
    {
        $easy = Exam::factory()->create(['subject_id' => $this->subject->id]);
        $hard = Exam::factory()->create(['subject_id' => $this->subject->id]);
        $hard->update(['settings' => ['weight' => 3]]);

        $student = $this->member('Hoc Sinh Co Trong So');

        ExamAttempt::factory()->graded()->create([
            'subject_id' => $this->subject->id,
            'exam_id' => $easy->id,
            'student_id' => $student->id,
            'score' => 10,
            'max_score' => 10,
        ]);

        ExamAttempt::factory()->graded()->create([
            'subject_id' => $this->subject->id,
            'exam_id' => $hard->id,
            'student_id' => $student->id,
            'score' => 6,
            'max_score' => 10,
        ]);

        // (100×1 + 60×3) / 4 = 70, chứ không phải trung bình thường 80.
        $this->assertSame(70.0, $this->averageOf('Hoc Sinh Co Trong So'));
    }

    public function test_leaderboard_shows_student_avatars(): void
    {
        $exam = Exam::factory()->create(['subject_id' => $this->subject->id]);

        $withAvatar = $this->member('Hoc Sinh Co Anh');
        $withAvatar->forceFill(['avatar' => 'avatars/1/anh.jpg'])->save();

        ExamAttempt::factory()->graded()->create([
            'subject_id' => $this->subject->id,
            'exam_id' => $exam->id,
            'student_id' => $withAvatar->id,
            'score' => 8,
            'max_score' => 10,
        ]);

        $viewer = $this->member('Triệu Thường Chung');

        $this->actingAs($viewer);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Info::class)
            ->assertSee('avatars/1/anh.jpg', escape: false)
            ->assertSee('Hoc Sinh Co Anh');
    }

    public function test_lowest_exam_is_dropped_from_four_exams(): void
    {
        $exams = [
            Exam::factory()->create(['subject_id' => $this->subject->id]),
            Exam::factory()->create(['subject_id' => $this->subject->id]),
            Exam::factory()->create(['subject_id' => $this->subject->id]),
            Exam::factory()->create(['subject_id' => $this->subject->id]),
        ];

        $student = $this->member('Hoc Sinh Bốn Đề');

        foreach ([10, 9, 8, 1] as $index => $score) {
            ExamAttempt::factory()->graded()->create([
                'subject_id' => $this->subject->id,
                'exam_id' => $exams[$index]->id,
                'student_id' => $student->id,
                'score' => $score,
                'max_score' => 10,
            ]);
        }

        // Bỏ đề 10%, còn (100 + 90 + 80) / 3 = 90.
        $this->assertSame(90.0, $this->averageOf('Hoc Sinh Bốn Đề'));
    }

    public function test_hard_exam_weight_beats_farmed_easy_scores(): void
    {
        $easy = Exam::factory()->create(['subject_id' => $this->subject->id]);
        $hard = Exam::factory()->create(['subject_id' => $this->subject->id]);
        $hard->update(['settings' => ['weight' => 3]]);

        $farmer = $this->member('Hoc Sinh Cay De Kho');
        $steady = $this->member('Hoc Sinh Vung Vang');

        $add = function (User $student, Exam $exam, int $attemptNo, int $score): void {
            ExamAttempt::factory()->graded()->create([
                'subject_id' => $this->subject->id,
                'exam_id' => $exam->id,
                'student_id' => $student->id,
                'attempt_no' => $attemptNo,
                'score' => $score,
                'max_score' => 10,
            ]);
        };

        // A: đề dễ 100% cày 3 lần + đề khó 50% lần đầu → (100 + 150) / 4 = 62.5.
        $add($farmer, $easy, 1, 10);
        $add($farmer, $easy, 2, 10);
        $add($farmer, $easy, 3, 10);
        $add($farmer, $hard, 1, 5);

        // B: đề dễ 70% + đề khó 80%, mỗi đề một lần → (70 + 240) / 4 = 77.5.
        $add($steady, $easy, 1, 7);
        $add($steady, $hard, 1, 8);

        $this->assertSame(62.5, $this->averageOf('Hoc Sinh Cay De Kho'));
        $this->assertSame(77.5, $this->averageOf('Hoc Sinh Vung Vang'));
    }
}
