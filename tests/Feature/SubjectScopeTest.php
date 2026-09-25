<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\TeamMembership;
use App\Models\User;
use App\Support\SubjectContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_scope_limits_queries_to_active_subject(): void
    {
        $math = Subject::factory()->create();
        $physics = Subject::factory()->create();

        TeamMembership::factory()->create(['subject_id' => $math->id]);
        TeamMembership::factory()->create(['subject_id' => $physics->id]);

        $context = app(SubjectContext::class);

        $context->set($math->id);
        $this->assertSame(1, TeamMembership::query()->count());
        $this->assertSame($math->id, TeamMembership::query()->first()->subject_id);

        $context->set($physics->id);
        $this->assertSame($physics->id, TeamMembership::query()->first()->subject_id);

        $context->clear();
        $this->assertSame(2, TeamMembership::query()->count());
    }

    public function test_without_subject_scope_returns_every_subject(): void
    {
        $math = Subject::factory()->create();
        $physics = Subject::factory()->create();

        TeamMembership::factory()->create(['subject_id' => $math->id]);
        TeamMembership::factory()->create(['subject_id' => $physics->id]);

        app(SubjectContext::class)->set($math->id);

        $this->assertSame(1, TeamMembership::query()->count());
        $this->assertSame(2, TeamMembership::query()->withoutSubjectScope()->count());
    }

    public function test_subject_id_is_auto_filled_from_context(): void
    {
        $subject = Subject::factory()->create();
        $student = User::factory()->student()->create();

        app(SubjectContext::class)->set($subject->id);

        $membership = TeamMembership::create([
            'student_id' => $student->id,
            'joined_at' => now(),
        ]);

        $this->assertSame($subject->id, $membership->subject_id);
    }

    public function test_user_subject_access_rules(): void
    {
        $math = Subject::factory()->create();
        $physics = Subject::factory()->create();

        $teacher = User::factory()->teacher($math)->create();
        $admin = User::factory()->superAdmin()->create();

        $this->assertTrue($teacher->canAccessSubject($math->id));
        $this->assertFalse($teacher->canAccessSubject($physics->id));
        $this->assertFalse($teacher->canAccessSubject(null));

        $this->assertTrue($admin->canAccessSubject($math->id));
        $this->assertTrue($admin->canAccessSubject(null));
    }
}
