<?php

namespace Tests\Feature\Teacher;

use App\Enums\SubjectFeature;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_is_forbidden_when_subject_feature_disabled(): void
    {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teacher($subject)->create();

        $subject->features()->where('feature', SubjectFeature::Exams->value)->update(['is_enabled' => false]);

        $this->actingAs($teacher)->get(route('studio.exams'))->assertForbidden();
        $this->actingAs($teacher)->get(route('studio.questions'))->assertOk();
    }

    public function test_route_is_allowed_when_feature_enabled(): void
    {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teacher($subject)->create();

        $this->actingAs($teacher)->get(route('studio'))->assertOk();
        $this->actingAs($teacher)->get(route('studio.questions'))->assertOk();
        $this->actingAs($teacher)->get(route('studio.exams'))->assertOk();
        $this->actingAs($teacher)->get(route('studio.assignments'))->assertOk();
        $this->actingAs($teacher)->get(route('studio.documents'))->assertOk();
        $this->actingAs($teacher)->get(route('students'))->assertOk();
    }
}
