<?php

namespace Tests\Feature\Notebook;

use App\Enums\ArtifactType;
use App\Enums\QuestionType;
use App\Models\AiProvider;
use App\Models\Exam;
use App\Models\Notebook;
use App\Models\NotebookArtifact;
use App\Models\Question;
use App\Models\Subject;
use App\Services\Notebook\ArtifactGenerator;
use App\Services\Notebook\ArtifactPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrueFalseArtifactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    private function generator(): ArtifactGenerator
    {
        return app(ArtifactGenerator::class);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function fakeQuestions(array $items): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'model' => 'openrouter/free',
                'choices' => [['message' => ['content' => json_encode($items, JSON_UNESCAPED_UNICODE)]]],
                'usage' => ['total_tokens' => 30],
            ], 200),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function generate(array $items, string $questionType = 'true_false'): array
    {
        $notebook = Notebook::factory()->create();

        return $this->generator()->generate(
            $notebook,
            ArtifactType::Questions,
            ['count' => count($items), 'question_type' => $questionType],
        )['payload']['items'];
    }

    public function test_prompt_asks_the_model_for_a_true_false_schema(): void
    {
        $hint = (new \ReflectionClass(ArtifactGenerator::class))
            ->getMethod('schemaHint')
            ->invoke($this->generator(), ArtifactType::Questions);

        $this->assertStringContainsString('true_false', $hint);
        $this->assertStringContainsString('"true"', $hint);
        $this->assertStringContainsString('"false"', $hint);
    }

    public function test_generated_true_false_questions_are_normalized(): void
    {
        $this->fakeQuestions([
            [
                'type' => 'true_false',
                'content' => 'Nước sôi ở 100°C.',
                'options' => [],
                'answer' => 'Đúng',
                'explanation' => 'Đúng ở áp suất khí quyển.',
                'difficulty' => 'easy',
                'points' => 1,
                'topic' => 'Nhiệt độ',
            ],
            [
                'type' => 'true_false',
                'content' => 'Sao Hỏa nằm gần Mặt Trời hơn Trái Đất.',
                'answer' => 'false',
                'difficulty' => 'easy',
                'points' => 1,
            ],
        ]);

        $items = $this->generate([
            ['type' => 'true_false'],
            ['type' => 'true_false'],
        ]);

        $this->assertCount(2, $items);
        $this->assertSame(QuestionType::TrueFalse->value, $items[0]['type']);
        $this->assertSame(QuestionType::TRUE, $items[0]['answer'], '"Đúng" phải về giá trị chuẩn');
        $this->assertSame(QuestionType::FALSE, $items[1]['answer']);
        $this->assertSame('Nhiệt độ', $items[0]['topic']);
        $this->assertSame([], $items[0]['options'], 'Câu đúng/sai không được giữ lựa chọn');
        $this->assertSame([], $items[1]['options']);
    }

    public function test_a_true_false_question_with_options_instead_of_answer_is_recovered(): void
    {
        // AI hay trả câu đúng/sai kèm hai lựa chọn "Đúng"/"Sai" thay vì đặt `answer`.
        $this->fakeQuestions([[
            'type' => 'true_false',
            'content' => 'Một mệnh đề bất kỳ.',
            'options' => [
                ['content' => 'Đúng', 'is_correct' => true],
                ['content' => 'Sai', 'is_correct' => false],
            ],
            'answer' => '',
            'difficulty' => 'medium',
            'points' => 1,
        ]]);

        $item = $this->generate([['type' => 'true_false']])[0];

        $this->assertSame(QuestionType::TRUE, $item['answer']);
        $this->assertSame([], $item['options']);
    }

    public function test_non_multiple_choice_questions_never_keep_options(): void
    {
        $this->fakeQuestions([
            [
                'type' => 'essay',
                'content' => 'Phân tích nguyên nhân.',
                'options' => [['content' => 'A', 'is_correct' => true]],
                'answer' => 'Bài luận mẫu.',
                'difficulty' => 'hard',
                'points' => 4,
            ],
            [
                'type' => 'fill_blank',
                'content' => '2 + 2 = ...',
                'options' => [],
                'answer' => '4',
                'difficulty' => 'easy',
                'points' => 1,
            ],
        ]);

        $items = $this->generate([
            ['type' => 'essay'],
            ['type' => 'fill_blank'],
        ], 'mixed');

        foreach ($items as $item) {
            $this->assertSame([], $item['options'], $item['type'].' không được có lựa chọn');
        }
    }

    public function test_prompt_tells_the_model_true_false_needs_no_options(): void
    {
        $this->fakeQuestions([[
            'type' => 'true_false',
            'content' => 'Một mệnh đề bất kỳ.',
            'answer' => 'true',
            'difficulty' => 'medium',
            'points' => 1,
        ]]);

        $this->generate([['type' => 'true_false']], 'true_false');

        Http::assertSent(function (HttpRequest $request): bool {
            $body = $request->data();

            // Yêu cầu bỏ lựa chọn cho câu đúng/sai nằm ở định dạng đầu ra, tức
            // tin nhắn của người dùng chứ không phải system prompt.
            $user = (string) ($body['messages'][1]['content'] ?? '');

            $this->assertStringContainsString('true_false', $user);
            $this->assertStringContainsString('mệnh đề', $user);

            return true;
        });
    }

    public function test_publisher_creates_a_true_false_question_without_options(): void
    {
        $subject = Subject::factory()->create();
        $notebook = Notebook::factory()->create(['subject_id' => $subject->id]);

        $artifact = NotebookArtifact::create([
            'notebook_id' => $notebook->id,
            'subject_id' => $subject->id,
            'user_id' => $notebook->owner_id,
            'type' => ArtifactType::Questions->value,
            'title' => 'Câu đúng sai',
            'status' => 'draft',
            'payload' => ['items' => [[
                'type' => QuestionType::TrueFalse->value,
                'content' => 'Trái Đất là hình cầu.',
                'options' => [],
                'answer' => QuestionType::TRUE,
                'explanation' => 'Đúng.',
                'difficulty' => 'easy',
                'points' => 1,
                'topic' => 'Vũ trụ',
                'included' => true,
            ]]],
        ]);

        app(ArtifactPublisher::class)->publish($artifact, true);

        $question = Question::query()->firstOrFail();

        $this->assertSame(QuestionType::TrueFalse, $question->type);
        $this->assertSame(QuestionType::TRUE, $question->answer);
        $this->assertSame(0, $question->options()->count());
        $this->assertSame('Vũ trụ', $question->topic);
    }

    public function test_publisher_converts_a_legacy_true_false_section_into_a_cluster(): void
    {
        $subject = Subject::factory()->create();
        $notebook = Notebook::factory()->create(['subject_id' => $subject->id]);

        $artifact = NotebookArtifact::create([
            'notebook_id' => $notebook->id,
            'subject_id' => $subject->id,
            'user_id' => $notebook->owner_id,
            'type' => ArtifactType::Exam->value,
            'title' => 'Đề đúng sai cũ',
            'status' => 'draft',
            'payload' => [
                'settings' => ['duration_minutes' => 45],
                'sections' => [[
                    'title' => 'Phần II',
                    'instructions' => 'Đoạn: Sản lượng tăng đều qua các năm.',
                    'questions' => [
                        $this->legacyTrueFalse(QuestionType::TRUE, 'Sản lượng tăng đều'),
                        $this->legacyTrueFalse(QuestionType::FALSE, 'Xuất khẩu giảm'),
                        $this->legacyTrueFalse(QuestionType::TRUE, 'Giá cả ổn định'),
                        $this->legacyTrueFalse(QuestionType::FALSE, 'Nhập khẩu tăng'),
                    ],
                ]],
            ],
        ]);

        app(ArtifactPublisher::class)->publish($artifact, true);

        $question = Question::query()->where('type', QuestionType::TrueFalseCluster->value)->firstOrFail();

        $this->assertSame(0, Question::query()->where('type', QuestionType::TrueFalse->value)->count());
        $this->assertSame('Đoạn: Sản lượng tăng đều qua các năm.', $question->content);
        $this->assertSame(
            ['Sản lượng tăng đều', 'Xuất khẩu giảm', 'Giá cả ổn định', 'Nhập khẩu tăng'],
            $question->options()->orderBy('order')->pluck('content')->all(),
        );
        $this->assertSame(
            [true, false, true, false],
            $question->options()->orderBy('order')->get()
                ->map(fn ($option): bool => (bool) $option->is_correct)->all(),
        );

        $exam = Exam::query()->firstOrFail();
        $examSection = $exam->sections()->firstOrFail();

        $this->assertSame('', (string) $examSection->instructions, 'Ngữ cảnh đã chuyển vào từng chùm');
        $this->assertSame($examSection->id, $exam->examQuestions()->firstOrFail()->exam_section_id);
        $this->assertEquals(1.0, (float) $exam->fresh()->total_points);
    }

    /**
     * Câu đúng/sai lẻ theo dạng bản nháp trước khi có chuẩn chùm BGD.
     *
     * @return array<string, mixed>
     */
    private function legacyTrueFalse(string $answer, string $content): array
    {
        return [
            'type' => QuestionType::TrueFalse->value,
            'content' => $content,
            'options' => [],
            'answer' => $answer,
            'explanation' => '',
            'difficulty' => 'easy',
            'points' => 0.25,
            'topic' => '',
            'included' => true,
        ];
    }
}
