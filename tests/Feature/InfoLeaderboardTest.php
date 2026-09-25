<?php

namespace Tests\Feature;

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

    public function test_leaderboard_orders_members_by_total_score(): void
    {
        $subject = Subject::factory()->create();
        $exam = Exam::factory()->create(['subject_id' => $subject->id]);

        $high = User::factory()->student($subject)->create(['name' => 'Hoc Sinh Cao']);
        $low = User::factory()->student($subject)->create(['name' => 'Hoc Sinh Thap']);

        foreach ([$high, $low] as $student) {
            TeamMembership::query()->create([
                'subject_id' => $subject->id,
                'student_id' => $student->id,
                'status' => 'active',
                'joined_at' => now(),
            ]);
        }

        ExamAttempt::factory()->graded()->create([
            'subject_id' => $subject->id,
            'exam_id' => $exam->id,
            'student_id' => $high->id,
            'score' => 9,
            'max_score' => 10,
        ]);

        ExamAttempt::factory()->graded()->create([
            'subject_id' => $subject->id,
            'exam_id' => $exam->id,
            'student_id' => $low->id,
            'score' => 3,
            'max_score' => 10,
        ]);

        $viewer = User::factory()->student($subject)->create();
        TeamMembership::query()->create([
            'subject_id' => $subject->id,
            'student_id' => $viewer->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer);
        app(SubjectContext::class)->set($subject->id);

        Livewire::test(Info::class)
            ->assertSeeInOrder(['Hoc Sinh Cao', 'Hoc Sinh Thap'])
            ->assertSee('Bảng xếp hạng điểm tổng');
    }
}
