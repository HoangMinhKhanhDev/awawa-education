<?php

namespace Database\Factories;

use App\Enums\MembershipStatus;
use App\Models\Subject;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamMembership>
 */
class TeamMembershipFactory extends Factory
{
    protected $model = TeamMembership::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'student_id' => User::factory()->student(),
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ];
    }
}
