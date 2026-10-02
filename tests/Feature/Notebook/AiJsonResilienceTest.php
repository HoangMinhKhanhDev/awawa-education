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
use Illuminate\Http\Client\Request as HttpRequest;
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

    public function test_first_json_generation_request_explicitly_requires_strict_json(): void
    {
        Http::preventStrayRequests();
        $this->fakeResponses([[
            'model' => 'openrouter/free',
            'choices' => [['message' => ['content' => $this->flashcardJson()]]],
            'usage' => ['total_tokens' => 20],
        ]]);

        $this->generateFlashcards();

        Http::assertSent(fn (HttpRequest $request): bool => str_contains(
            (string) ($request->data()['messages'][1]['content'] ?? ''),
            'QUAN TRỌNG: trả về DUY NHẤT đúng một JSON hợp lệ',
        ));
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
            .'{"type":"essay","content":"Câu 3 bị cụt';

        $full = '{"title":"Đề kiểm tra","description":"Đề do AI soạn","sections":[{"title":"PHẦN I","questions":['
            .'{"type":"essay","content":"Câu 1","difficulty":"easy","points":1,"options":[]},'
            .'{"type":"essay","content":"Câu 2","difficulty":"easy","points":1,"options":[]},'
            .'{"type":"essay","content":"Câu 3 đầy đủ","difficulty":"easy","points":1,"options":[]}]}]}';

        // Lần 1 cụt giữa câu 3: salvage cứu được 2 câu nguyên nhưng vẫn thiếu
        // 1 câu so với yêu cầu nên thử lại; lần 2 đủ thì nhận.
        $this->fakeResponses([
            [
                'model' => 'openrouter/free',
                'choices' => [['message' => ['content' => $truncated]]],
                'usage' => ['total_tokens' => 20],
            ],
            [
                'model' => 'openrouter/free',
                'choices' => [['message' => ['content' => $full]]],
                'usage' => ['total_tokens' => 25],
            ],
        ]);

        $data = app(ArtifactGenerator::class)->generate(
            $this->notebook,
            ArtifactType::Exam,
            ['instruction' => 'Soạn đề', 'exam_sections' => 1, 'exam_questions_per_section' => 3, 'exam_total_points' => 6],
            $this->subject->id,
            $this->teacher->id,
        );

        $questions = $data['payload']['sections'][0]['questions'];

        $this->assertCount(3, $questions);
        $this->assertSame('Câu 3 đầy đủ', $questions[2]['content']);
        Http::assertSentCount(2);
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

    /**
     * @param  array<int, array<string, mixed>>  $questions
     * @return array<string, mixed>
     */
    private function generateOneSectionExam(array $questions): array
    {
        $this->fakeResponses([[
            'model' => 'openrouter/free',
            'choices' => [['message' => ['content' => json_encode([
                'title' => 'Đề kiểm tra',
                'description' => 'Đề do AI soạn',
                'sections' => [['title' => 'PHẦN I', 'questions' => $questions]],
            ], JSON_UNESCAPED_UNICODE)]]],
            'usage' => ['total_tokens' => 40],
        ]]);

        return app(ArtifactGenerator::class)->generate(
            $this->notebook,
            ArtifactType::Exam,
            ['instruction' => 'Soạn đề', 'exam_sections' => 1, 'exam_questions_per_section' => count($questions), 'exam_total_points' => 10],
            $this->subject->id,
            $this->teacher->id,
        )['payload'];
    }

    public function test_correct_option_survives_shuffling_and_letter_answer_is_dropped(): void
    {
        $payload = $this->generateOneSectionExam([[
            'type' => 'multiple_choice',
            'content' => '2 + 2 bằng mấy?',
            'options' => [
                ['content' => '4', 'is_correct' => true],
                ['content' => '5', 'is_correct' => false],
                ['content' => '6', 'is_correct' => false],
                ['content' => '7', 'is_correct' => false],
            ],
            'answer' => 'A',
            'difficulty' => 'easy',
            'points' => 1,
        ]]);

        $question = $payload['sections'][0]['questions'][0];

        // Đáp án đúng vẫn đúng 1, nội dung các lựa chọn giữ nguyên (chỉ đổi chỗ),
        // chữ cái "A" do AI ghi thêm bị xóa để bảng đáp án không toàn chữ cái.
        $this->assertSame('', $question['answer']);
        $this->assertSame(1, collect($question['options'])->where('is_correct', true)->count());
        $this->assertEqualsCanonicalizing(
            ['4', '5', '6', '7'],
            array_column($question['options'], 'content'),
        );
        $this->assertSame('4', collect($question['options'])->firstWhere('is_correct')['content']);
    }

    public function test_true_false_disguised_as_multiple_choice_is_converted(): void
    {
        $payload = $this->generateOneSectionExam([[
            'type' => 'multiple_choice',
            'content' => 'Tăng góp đổi thu nhập giúp cải thiện đời sống?',
            'options' => [
                ['content' => 'Đúng', 'is_correct' => true],
                ['content' => 'Sai', 'is_correct' => false],
            ],
            'answer' => 'A',
            'difficulty' => 'medium',
            'points' => 1,
        ]]);

        $question = $payload['sections'][0]['questions'][0];

        $this->assertSame('true_false', $question['type']);
        $this->assertSame('true', $question['answer']);
        $this->assertSame([], $question['options']);
    }

    public function test_true_false_with_options_and_letter_answer_is_repaired(): void
    {
        $payload = $this->generateOneSectionExam([[
            'type' => 'true_false',
            'content' => 'Việc tặng tỉ lệ cây rừng giúp tăng độ che phủ?',
            'options' => [
                ['content' => 'Đúng', 'is_correct' => false],
                ['content' => 'Sai', 'is_correct' => true],
            ],
            'answer' => 'B',
            'difficulty' => 'medium',
            'points' => 1,
        ]]);

        $question = $payload['sections'][0]['questions'][0];

        $this->assertSame('true_false', $question['type']);
        $this->assertSame('false', $question['answer']);
        $this->assertSame([], $question['options']);
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

    public function test_large_exam_is_split_into_multiple_ai_calls(): void
    {
        // Ép ngân sách một lần gọi về 32 câu để đề 40 câu phải chia đợt,
        // bất kể NOTEBOOK_MAX_ARTIFACT_TOKENS ngoài .env là bao nhiêu.
        // 1.200 token phần mở đầu + 32 × 350 token/câu = 12.400.
        config()->set('awawa.notebook.max_artifact_tokens', 12400);
        $this->assertSame(32, ArtifactGenerator::questionsPerAiCall());

        $makeQuestions = fn (int $from, int $count): array => array_map(
            fn (int $i): array => [
                'type' => 'essay',
                'content' => 'Câu '.($from + $i),
                'difficulty' => 'medium',
                'points' => 1,
                'options' => [],
            ],
            range(0, $count - 1),
        );

        $makeResponse = fn (array $sections): array => [
            'model' => 'openrouter/free',
            'choices' => [['message' => ['content' => json_encode([
                'title' => 'Đề kiểm tra',
                'description' => 'Đề do AI soạn',
                'sections' => $sections,
            ], JSON_UNESCAPED_UNICODE)]]],
            'usage' => ['total_tokens' => 60],
        ];

        // 2 phần × 20 câu = 40, vượt ngân sách một lần gọi (32) nên phải chia
        // 2 đợt: đợt 1 soạn phần I (20 câu) + 12 câu đầu phần II, đợt 2 soạn
        // nốt 8 câu còn lại của phần II.
        $this->fakeResponses([
            $makeResponse([
                ['title' => 'PHẦN I', 'questions' => $makeQuestions(1, 20)],
                ['title' => 'PHẦN II', 'questions' => $makeQuestions(21, 12)],
            ]),
            $makeResponse([
                ['title' => 'PHẦN II', 'questions' => $makeQuestions(33, 8)],
            ]),
        ]);

        $data = app(ArtifactGenerator::class)->generate(
            $this->notebook,
            ArtifactType::Exam,
            ['instruction' => 'Soạn đề lớn', 'exam_sections' => 2, 'exam_questions_per_section' => 20, 'exam_total_points' => 10],
            $this->subject->id,
            $this->teacher->id,
        );

        $this->assertCount(2, $data['payload']['sections']);
        $this->assertCount(20, $data['payload']['sections'][0]['questions']);
        $this->assertCount(20, $data['payload']['sections'][1]['questions']);
        $this->assertSame('Câu 1', $data['payload']['sections'][0]['questions'][0]['content']);
        $this->assertSame('Câu 21', $data['payload']['sections'][1]['questions'][0]['content']);

        // Điểm chia đều theo toàn đề: 10 điểm / 40 câu.
        $this->assertSame(0.25, (float) $data['payload']['sections'][0]['questions'][0]['points']);

        Http::assertSentCount(2);
    }

    /**
     * Hồi quy cho lỗi "AI chỉ soạn được 8/10 câu" trên production.
     *
     * Đo từ `ai_usage_logs`: một đề 10 câu bị cắt đúng ở trần 2.700 token và chỉ
     * ra được 8 câu, tức thực tế mỗi câu mất ~337 token chứ không phải 150. Trần
     * tính thiếu thì model bị cắt giữa chừng, app gọi lại lần hai với cùng giới
     * hạn, vẫn thiếu, rồi báo lỗi — mất đôi thời gian và vẫn hỏng.
     */
    public function test_token_budget_leaves_room_for_a_full_exam(): void
    {
        $generator = app(ArtifactGenerator::class);
        $maxTokens = new \ReflectionMethod($generator, 'maxTokensFor');

        $cap = ArtifactGenerator::tokenCap();

        foreach ([5, 10, 13] as $questions) {
            $budget = $maxTokens->invoke($generator, ArtifactType::Exam, [
                'exam_sections' => 1,
                'exam_questions_per_section' => $questions,
            ]);

            $this->assertLessThanOrEqual($cap, $budget, 'Trần token không được vượt trần cấu hình.');

            // Đo thực tế trên host: ~337 token/câu. Cần dư tối thiểu 300/câu để
            // câu cuối không bị cắt.
            $this->assertGreaterThanOrEqual(
                $questions * 300,
                $budget,
                "Đề {$questions} câu phải còn dư chỗ cho câu dài, nếu không sẽ thiếu câu.",
            );
        }

        // Một đề nhỏ phải nằm gọn trong một lần gọi: không chunking.
        $this->assertGreaterThanOrEqual(10, ArtifactGenerator::questionsPerAiCall());
    }

    public function test_max_tokens_grows_with_the_exam_size(): void
    {
        $generator = app(ArtifactGenerator::class);
        $method = new \ReflectionMethod($generator, 'maxTokensFor');

        $small = $method->invoke($generator, ArtifactType::Exam, ['exam_sections' => 1, 'exam_questions_per_section' => 5]);
        $big = $method->invoke($generator, ArtifactType::Exam, ['exam_sections' => 3, 'exam_questions_per_section' => 10]);

        $this->assertGreaterThan($small, $big);
        $this->assertLessThanOrEqual(ArtifactGenerator::tokenCap(), $big);
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

    public function test_json_generation_requests_json_mode_from_the_provider(): void
    {
        Http::preventStrayRequests();
        $this->fakeResponses([[
            'model' => 'openrouter/free',
            'choices' => [['message' => ['content' => $this->flashcardJson()]]],
            'usage' => ['total_tokens' => 20],
        ]]);

        $this->generateFlashcards();

        Http::assertSent(function (HttpRequest $request): bool {
            $format = $request->data()['response_format'] ?? null;

            return is_array($format) && ($format['type'] ?? null) === 'json_object';
        });
    }

    public function test_json_mode_rejection_falls_back_to_a_plain_request(): void
    {
        Http::preventStrayRequests();

        $fake = Http::sequence()
            ->push(['error' => ['message' => 'response_format json_object is not supported']], 400)
            ->push([
                'model' => 'openrouter/free',
                'choices' => [['message' => ['content' => $this->flashcardJson()]]],
                'usage' => ['total_tokens' => 20],
            ], 200);

        Http::fake(['openrouter.ai/*' => $fake]);

        $payload = $this->generateFlashcards();

        $this->assertSame('Phép tính?', $payload['cards'][0]['front']);

        $sent = Http::recorded();

        $this->assertCount(2, $sent);
        $this->assertSame('json_object', $sent[0][0]->data()['response_format']['type'] ?? null);
        $this->assertArrayNotHasKey('response_format', $sent[1][0]->data());
    }
}
