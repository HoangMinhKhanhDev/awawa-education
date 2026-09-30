<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Livewire\Teacher\ClassStats;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Subject;
use App\Models\TeamMembership;
use App\Models\User;
use App\Services\StudentAbility;
use App\Support\SafeCache;
use App\Support\SubjectContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cache file trên host serialize giá trị. Sau mỗi lần deploy, cache cũ chứa
 * class PHP cũ nên unserialize ra `__PHP_Incomplete_Class`; chỗ có kiểu trả về
 * nghiêm ngặt sẽ ném TypeError và làm trắng cả trang.
 *
 * `phpunit.xml` đặt CACHE_STORE=array (không serialize) nên test phải ép
 * `file` mới tái hiện được lỗi thật.
 */
class CacheSerializationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'file');
        Cache::clear();
    }

    protected function tearDown(): void
    {
        Cache::clear();

        parent::tearDown();
    }

    public function test_ability_rows_survive_being_read_from_cache_twice(): void
    {
        [$subject, $student, $exam] = $this->seedGradedAttempt();

        $ability = app(StudentAbility::class);

        $first = $ability->rows($subject->id);
        $this->assertCount(1, $first);
        $this->assertSame(50.0, $first->first()['average']);

        // Lần đọc thứ hai mới đi qua serialize/unserialize thật.
        $second = $ability->rows($subject->id);

        $this->assertInstanceOf(Collection::class, $second);
        $this->assertCount(1, $second);
        $this->assertSame(50.0, $second->first()['average']);
        $this->assertIsArray($second->first()['details']);
    }

    public function test_ability_rows_recompute_when_the_cached_value_is_broken(): void
    {
        [$subject] = $this->seedGradedAttempt();

        $key = 'ability:v2:subject:'.$subject->id;

        // Giả lập cache do code cũ ghi: giá trị là object không còn tồn tại.
        $broken = (new \ReflectionClass(\stdClass::class))->newInstanceWithoutConstructor();
        Cache::put($key, $broken, 120);

        $rows = app(StudentAbility::class)->rows($subject->id);

        $this->assertInstanceOf(Collection::class, $rows);
    }

    public function test_class_stats_page_renders_after_its_cache_is_read_back(): void
    {
        [$subject, $teacher, $student, $exam] = $this->seedGradedAttempt();

        app(SubjectContext::class)->set($subject->id);

        Livewire::actingAs($teacher)
            ->test(ClassStats::class, ['subject' => $subject])
            ->assertOk();

        // Lần thứ hai đọc từ cache: trước đây đây là chỗ nổ TypeError.
        Livewire::actingAs($teacher)
            ->test(ClassStats::class, ['subject' => $subject])
            ->assertOk()
            ->assertSee($exam->title);
    }

    public function test_safe_cache_replaces_broken_values(): void
    {
        $calls = 0;

        $value = SafeCache::remember('probe:key', 60, function () use (&$calls) {
            $calls++;

            return ['ok' => true];
        });

        $this->assertSame(['ok' => true], $value);
        $this->assertSame(1, $calls);

        // Ghi đè bằng giá trị hỏng rồi đọc lại: phải tính lại, không ném lỗi.
        $broken = (new \ReflectionClass(\stdClass::class))->newInstanceWithoutConstructor();
        Cache::put('probe:key', $broken, 60);

        $this->assertSame(['ok' => true], SafeCache::remember('probe:key', 60, function () use (&$calls) {
            $calls++;

            return ['ok' => true];
        }));

        $this->assertSame(2, $calls);
    }

    public function test_safe_cache_keeps_healthy_values(): void
    {
        $calls = 0;
        $compute = function () use (&$calls): array {
            $calls++;

            return ['ok' => true];
        };

        SafeCache::remember('probe:key', 60, $compute);
        SafeCache::remember('probe:key', 60, $compute);

        $this->assertSame(1, $calls, 'Giá trị cache tốt thì không tính lại.');
    }

    /**
     * @return array{0: Subject, 1: User, 2: User, 3: Exam}
     */
    private function seedGradedAttempt(): array
    {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teacher($subject)->create();
        $student = User::factory()->student($subject)->create();

        TeamMembership::query()->create([
            'subject_id' => $subject->id,
            'student_id' => $student->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $exam = Exam::factory()->create(['subject_id' => $subject->id]);

        ExamAttempt::query()->create([
            'subject_id' => $subject->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'status' => AttemptStatus::Graded->value,
            'attempt_no' => 1,
            'score' => 5,
            'max_score' => 10,
            'started_at' => now()->subHour(),
            'submitted_at' => now()->subHour(),
        ]);

        return [$subject, $teacher, $student, $exam];
    }
}
