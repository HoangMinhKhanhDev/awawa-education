<?php

namespace Tests\Feature\Notebook;

use App\Enums\ArtifactType;
use App\Enums\ExamStatus;
use App\Livewire\Notebook\Studio;
use App\Models\AiProvider;
use App\Models\Exam;
use App\Models\Notebook;
use App\Models\NotebookArtifact;
use App\Models\Subject;
use App\Models\User;
use App\Services\Ai\AiException;
use App\Services\Notebook\ArtifactGenerator;
use App\Services\Notebook\ArtifactPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class AiJsonResilienceTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private User $teacher;

    private Notebook $notebook;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = Subject::factory()->create();
        $this->subject->features()->where('feature', 'exams')->update(['is_enabled' => true]);
        $this->subject->forgetFeatureCache();

        $this->teacher = User::factory()->teacher($this->subject)->create();
        $this->notebook = Notebook::factory()->create([
            'subject_id' => $this->subject->id,
            'owner_id' => $this->teacher->id,
        ]);

        $this->actingAs($this->teacher);

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

    /**
     * @param  array<int, mixed>  $sequence
     */
    private function fakeResponses(array $sequence): void
    {
        $fake = Http::sequence();

        foreach ($sequence as $item) {
            $fake->push($item);
        }

        Http::fake(['openrouter.ai/*' => $fake]);
    }

    private function flashcardJson(string $front = 'Phép tính?'): string
    {
        return json_encode([
            ['front' => $front, 'back' => 'Cộng trừ nhân chia.'],
        ], JSON_UNESCAPED_UNICODE);
    }

    public function test_it_accepts_json_wrapped_in_a_code_fence(): void
    {
        $this->fakeResponses([[
            'model' => 'openrouter/free',
            'choices' => [['message' => ['content' => "```json\n".$this->flashcardJson()."\n```"]]],
            'usage' => ['total_tokens' => 20],
        ]]);

        $payload = $this->generateFlashcards();

        $this->assertSame('Phép tính?', $payload['cards'][0]['front']);
    }

    public function test_it_accepts_json_with_trailing_text_after_it(): void
    {
        $this->fakeResponses([[
            'model' => 'openrouter/free',
            'choices' => [['message' => ['content' => 'Đây là kết quả: '.$this->flashcardJson().' — hết, cảm ơn }']]],
            'usage' => ['total_tokens' => 20],
        ]]);

        $payload = $this->generateFlashcards();

        $this->assertSame('Phép tính?', $payload['cards'][0]['front']);
    }

    public function test_it_accepts_json_with_trailing_commas(): void
    {
        $content = '[{"front":"A","back":"B",},]';

        $this->fakeResponses([[
            'model' => 'openrouter/free',
            'choices' => [['message' => ['content' => $content]]],
            'usage' => ['total_tokens' => 20],
        ]]);

        $payload = $this->generateFlashcards();

        $this->assertSame('A', $payload['cards'][0]['front']);
    }

    public function test_it_accepts_json_that_was_escaped_twice(): void
    {
        $this->fakeResponses([[
            'model' => 'openrouter/free',
            'choices' => [['message' => ['content' => json_encode($this->flashcardJson(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]]],
            'usage' => ['total_tokens' => 20],
        ]]);

        $payload = $this->generateFlashcards();

        $this->assertSame('Phép tính?', $payload['cards'][0]['front']);
    }

    public function test_it_salvages_a_truncated_exam_response(): void
    {
        $truncated = '{"sections":[{"title":"PHẦN I","questions":['
            .'{"type":"essay","content":"Câu 1","difficulty":"easy","points":1,"options":[]},'
            .'{"type":"essay","content":"Câu 2","difficulty":"easy","points":1,"options":[]},'
            .'{"type":"essay","content":"Câu 3 bị cắ';

        $this->fakeResponses([[
            'model' => 'openrouter/free',
            'choices' => [['message' => ['content' => $truncated]]],
            'usage' => ['total_tokens' => 20],
        ]]);

        $data = app(ArtifactGenerator::class)->generate(
            $this->notebook,
            ArtifactType::Exam,
            ['instruction' => 'Soạn đề', 'exam_sections' => 1, 'exam_questions_per_section' => 3, 'exam_total_points' => 6],
            $this->subject->id,
            $this->teacher->id,
        );

        $questions = $data['payload']['sections'][0]['questions'];

        $this->assertCount(2, $questions, 'Chỉ giữ lại những câu không bị cắt.');
        $this->assertSame('Câu 1', $questions[0]['content']);
        $this->assertSame('Câu 2', $questions[1]['content']);
        Http::assertSentCount(1);
    }

    public function test_it_retries_once_when_json_is_invalid(): void
    {
        $this->fakeResponses([
            ['model' => 'openrouter/free', 'choices' => [['message' => ['content' => 'Xin lỗi, tôi không làm được.']]], 'usage' => ['total_tokens' => 5]],
            ['model' => 'openrouter/free', 'choices' => [['message' => ['content' => $this->flashcardJson()]]], 'usage' => ['total_tokens' => 20]],
        ]);

        $payload = $this->generateFlashcards();

        $this->assertSame('Phép tính?', $payload['cards'][0]['front']);
        Http::assertSentCount(2);
    }

    public function test_it_does_not_retry_more_than_once(): void
    {
        $this->fakeResponses([
            ['model' => 'openrouter/free', 'choices' => [['message' => ['content' => 'không phải JSON']]], 'usage' => ['total_tokens' => 5]],
            ['model' => 'openrouter/free', 'choices' => [['message' => ['content' => 'vẫn không phải JSON']]], 'usage' => ['total_tokens' => 5]],
        ]);

        $this->expectException(\RuntimeException::class);

        try {
            $this->generateFlashcards();
        } finally {
            Http::assertSentCount(2);
        }
    }

    public function test_it_does_not_retry_when_the_provider_fails(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response(['error' => ['message' => 'boom']], 500),
        ]);

        $this->expectException(AiException::class);

        try {
            $this->generateFlashcards();
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_an_exam_returned_as_a_flat_question_list_is_accepted(): void
    {
        $content = json_encode(['questions' => [
            ['type' => 'essay', 'content' => 'Câu 1', 'difficulty' => 'easy', 'points' => 1, 'options' => []],
        ]], JSON_UNESCAPED_UNICODE);

        $this->fakeResponses([[
            'model' => 'openrouter/free',
            'choices' => [['message' => ['content' => $content]]],
            'usage' => ['total_tokens' => 20],
        ]]);

        $data = app(ArtifactGenerator::class)->generate(
            $this->notebook,
            ArtifactType::Exam,
            ['instruction' => 'Soạn đề', 'exam_sections' => 1, 'exam_questions_per_section' => 1, 'exam_total_points' => 4],
            $this->subject->id,
            $this->teacher->id,
        );

        $this->assertCount(1, $data['payload']['sections']);
        $this->assertSame('Câu 1', $data['payload']['sections'][0]['questions'][0]['content']);
    }

    public function test_an_oversized_exam_is_refused_before_calling_the_ai(): void
    {
        Http::fake();

        $this->expectException(\RuntimeException::class);

        try {
            app(ArtifactGenerator::class)->generate(
                $this->notebook,
                ArtifactType::Exam,
                ['instruction' => 'Soạn đề lớn', 'exam_sections' => 6, 'exam_questions_per_section' => 30, 'exam_total_points' => 10],
                $this->subject->id,
                $this->teacher->id,
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_max_tokens_grows_with_the_exam_size(): void
    {
        $generator = app(ArtifactGenerator::class);
        $method = new \ReflectionMethod($generator, 'maxTokensFor');

        $small = $method->invoke($generator, ArtifactType::Exam, ['exam_sections' => 1, 'exam_questions_per_section' => 5]);
        $big = $method->invoke($generator, ArtifactType::Exam, ['exam_sections' => 3, 'exam_questions_per_section' => 10]);

        $this->assertGreaterThan($small, $big);
        $this->assertLessThanOrEqual(8000, $big);
    }

    public function test_deliver_exam_makes_the_exam_visible_to_students(): void
    {
        Notification::fake();

        $artifact = NotebookArtifact::create([
            'notebook_id' => $this->notebook->id,
            'subject_id' => $this->subject->id,
            'user_id' => $this->teacher->id,
            'type' => 'exam',
            'title' => 'Đề giữa kỳ',
            'text_content' => null,
            'status' => 'draft',
            'payload' => [
                'settings' => ['duration_minutes' => 45, 'total_points' => 4, 'shuffle_questions' => false, 'shuffle_options' => false],
                'sections' => [[
                    'title' => 'PHẦN I',
                    'instructions' => 'Chọn đáp án đúng',
                    'questions' => [[
                        'type' => 'essay',
                        'content' => 'Câu 1',
                        'options' => [],
                        'answer' => 'Trả lời',
                        'difficulty' => 'easy',
                        'points' => 4,
                        'included' => true,
                    ]],
                ]],
            ],
        ]);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('deliverExam', $artifact->id)
            ->assertSet('error', null);

        $exam = Exam::query()->firstOrFail();

        $this->assertSame(ExamStatus::Published, $exam->status);
        $this->assertSame(45, $exam->duration_minutes);
        $this->assertSame('published', $artifact->fresh()->status);
        $this->assertSame(1, $exam->examQuestions()->count());
    }

    public function test_students_only_see_exams_of_their_own_subject(): void
    {
        $student = User::factory()->student($this->subject)->create();

        $mine = Exam::create([
            'subject_id' => $this->subject->id,
            'created_by' => $this->teacher->id,
            'type' => 'exam',
            'title' => 'Đề của môn tôi',
            'status' => ExamStatus::Published,
        ]);

        $otherSubject = Subject::factory()->create();
        $foreign = Exam::create([
            'subject_id' => $otherSubject->id,
            'created_by' => $otherSubject->id,
            'type' => 'exam',
            'title' => 'Đề môn khác',
            'status' => ExamStatus::Published,
        ]);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Đề của môn tôi')
            ->assertDontSee('Đề môn khác');
    }

    public function test_notification_is_sent_only_when_delivering_immediately(): void
    {
        Notification::fake();

        $makeArtifact = function (): NotebookArtifact {
            return NotebookArtifact::create([
                'notebook_id' => $this->notebook->id,
                'subject_id' => $this->subject->id,
                'user_id' => $this->teacher->id,
                'type' => 'exam',
                'title' => 'Đề '.uniqid(),
                'status' => 'draft',
                'payload' => ['sections' => [[
                    'title' => 'PHẦN I',
                    'questions' => [[
                        'type' => 'essay',
                        'content' => 'Câu 1',
                        'options' => [],
                        'answer' => '',
                        'difficulty' => 'easy',
                        'points' => 1,
                        'included' => true,
                    ]],
                ]]],
            ]);
        };

        $publisher = app(ArtifactPublisher::class);

        $publisher->publish($makeArtifact(), true, false);
        Notification::assertNothingSent();
        $this->assertSame(ExamStatus::Draft, Exam::query()->firstOrFail()->status);

        $publisher->publish($makeArtifact(), true, true);
        $this->assertSame(ExamStatus::Published, Exam::query()->latest('id')->firstOrFail()->status);
    }

    /**
     * @return array<string, mixed>
     */
    private function generateFlashcards(): array
    {
        $data = app(ArtifactGenerator::class)->generate(
            $this->notebook,
            ArtifactType::Flashcards,
            ['instruction' => 'Soạn thẻ ghi nhớ'],
            $this->subject->id,
            $this->teacher->id,
        );

        return $data['payload'];
    }
}
