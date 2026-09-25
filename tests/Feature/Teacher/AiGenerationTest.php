<?php

namespace Tests\Feature\Teacher;

use App\Livewire\Teacher\AiGenerate;
use App\Models\AiProvider;
use App\Models\AiUsageLog;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Support\SubjectContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AiGenerationTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = Subject::factory()->create();
        $this->teacher = User::factory()->teacher($this->subject)->create();
        $this->actingAs($this->teacher);
        app(SubjectContext::class)->set($this->subject->id);
    }

    private function configureProvider(): void
    {
        AiProvider::create([
            'key' => 'openrouter',
            'label' => 'OpenRouter',
            'base_url' => 'https://openrouter.ai/api/v1',
            'api_key' => 'test-key',
            'default_model' => 'openrouter/free',
            'is_enabled' => true,
            'is_default' => true,
        ]);
    }

    private function fakeQuestionsResponse(): void
    {
        $questions = [
            [
                'type' => 'multiple_choice',
                'content' => 'Giá trị của 2 + 2 là?',
                'options' => [
                    ['content' => '3', 'is_correct' => false],
                    ['content' => '4', 'is_correct' => true],
                ],
                'answer' => '4',
                'explanation' => 'Cộng cơ bản.',
                'difficulty' => 'easy',
                'points' => 1,
                'topic' => 'Số học',
            ],
            [
                'type' => 'fill_blank',
                'content' => 'Thủ đô Việt Nam là ____',
                'answer' => 'Hà Nội',
                'explanation' => '',
                'difficulty' => 'easy',
                'points' => 1,
                'topic' => 'Địa lý',
            ],
        ];

        Http::fake([
            'openrouter.ai/*' => Http::response([
                'model' => 'openrouter/free',
                'choices' => [['message' => ['content' => json_encode($questions, JSON_UNESCAPED_UNICODE)]]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20, 'total_tokens' => 30],
            ], 200),
        ]);
    }

    public function test_teacher_can_generate_and_preview_questions(): void
    {
        $this->configureProvider();
        $this->fakeQuestionsResponse();

        $component = Livewire::test(AiGenerate::class)
            ->set('topic', 'Toán cơ bản')
            ->set('count', 2)
            ->call('generateQuestions')
            ->assertHasNoErrors()
            ->assertSet('aiError', null)
            ->assertSet('usedTokens', 30);

        $this->assertCount(2, $component->get('draft'));
    }

    public function test_importing_selected_questions_saves_to_bank(): void
    {
        $this->configureProvider();
        $this->fakeQuestionsResponse();

        Livewire::test(AiGenerate::class)
            ->set('topic', 'Toán cơ bản')
            ->set('count', 2)
            ->call('generateQuestions')
            ->set('selected', [0, 1])
            ->call('importSelected')
            ->assertRedirect(route('studio.questions'));

        $this->assertSame(2, Question::query()->where('subject_id', $this->subject->id)->count());

        $mc = Question::query()->where('type', 'multiple_choice')->firstOrFail();
        $this->assertCount(2, $mc->options);
        $this->assertSame(1, $mc->options->where('is_correct', true)->count());
    }

    public function test_usage_is_logged(): void
    {
        $this->configureProvider();
        $this->fakeQuestionsResponse();

        Livewire::test(AiGenerate::class)
            ->set('topic', 'Toán cơ bản')
            ->call('generateQuestions');

        $this->assertDatabaseHas('ai_usage_logs', [
            'subject_id' => $this->subject->id,
            'user_id' => $this->teacher->id,
            'provider_key' => 'openrouter',
            'is_success' => true,
        ]);

        $this->assertSame(30, (int) AiUsageLog::query()->sum('total_tokens'));
    }

    public function test_missing_provider_surfaces_error(): void
    {
        $component = Livewire::test(AiGenerate::class)
            ->set('topic', 'Toán cơ bản')
            ->call('generateQuestions');

        $this->assertNotNull($component->get('aiError'));
        $this->assertSame([], $component->get('draft'));
    }

    public function test_invalid_ai_payload_sets_error(): void
    {
        $this->configureProvider();

        Http::fake([
            'openrouter.ai/*' => Http::response([
                'model' => 'openrouter/free',
                'choices' => [['message' => ['content' => 'Xin chào, tôi không thể tạo JSON.']]],
                'usage' => ['total_tokens' => 5],
            ], 200),
        ]);

        $component = Livewire::test(AiGenerate::class)
            ->set('topic', 'Toán cơ bản')
            ->call('generateQuestions');

        $this->assertNotNull($component->get('aiError'));
    }
}
