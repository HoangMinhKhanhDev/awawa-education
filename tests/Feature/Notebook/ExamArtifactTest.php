<?php

namespace Tests\Feature\Notebook;

use App\Enums\ExamType;
use App\Livewire\Notebook\Studio;
use App\Models\AiProvider;
use App\Models\Exam;
use App\Models\ExamSection;
use App\Models\Notebook;
use App\Models\NotebookArtifact;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Services\Notebook\ArtifactGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ExamArtifactTest extends TestCase
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
     * Mở màn đề thi với các tuỳ chỉnh rồi bấm Tạo.
     *
     * @param  array<string, mixed>  $settings
     */
    private function generateExam(array $settings = []): Testable
    {
        $component = Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('selectType', 'exam');

        foreach ($settings as $name => $value) {
            $component->set($name, $value);
        }

        return $component
            ->set('instruction', 'Soạn đề kiểm tra chuyên đề 1')
            ->call('generate');
    }

    private function artifactOf(Testable $component): NotebookArtifact
    {
        return NotebookArtifact::query()->where('notebook_id', $this->notebook->id)->latest('id')->firstOrFail();
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function fakeExamJson(array $sections, string $title = 'Đề kiểm tra giữa kỳ'): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'model' => 'openrouter/free',
                'choices' => [['message' => ['content' => json_encode([
                    'title' => $title,
                    'description' => 'Đề do AI soạn',
                    'sections' => $sections,
                ], JSON_UNESCAPED_UNICODE)]]],
                'usage' => ['total_tokens' => 50],
            ], 200),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return array<string, mixed>
     */
    private function question(string $content, string $type = 'multiple_choice', array $options = [], float $points = 1): array
    {
        return [
            'type' => $type,
            'content' => $content,
            'options' => $options,
            'answer' => '',
            'explanation' => 'Giải thích ngắn',
            'difficulty' => 'medium',
            'points' => $points,
            'topic' => 'Chủ đề',
        ];
    }

    public function test_generated_exam_follows_the_requested_structure(): void
    {
        $this->fakeExamJson([
            [
                'title' => 'PHẦN I',
                'instructions' => 'Chọn một đáp án đúng nhất',
                'questions' => [
                    $this->question('Câu 1 nội dung?', 'multiple_choice', [
                        ['content' => 'A', 'is_correct' => false],
                        ['content' => 'B', 'is_correct' => true],
                        ['content' => 'C', 'is_correct' => false],
                        ['content' => 'D', 'is_correct' => false],
                    ], 3),
                    $this->question('Câu 2 nội dung?', 'essay', [], 3),
                ],
            ],
            [
                'title' => 'PHẦN II',
                'instructions' => 'Tự luận',
                'questions' => [
                    $this->question('Câu 3 nội dung?', 'essay', [], 3),
                    $this->question('Câu 4 nội dung?', 'essay', [], 3),
                ],
            ],
        ]);

        $component = $this->generateExam([
            'examSections' => 2,
            'examQuestionsPerSection' => 2,
            'examTotalPoints' => 8,
            'examDurationMinutes' => 60,
        ]);

        $component->assertHasNoErrors();

        $payload = $this->artifactOf($component)->payload;

        $this->assertCount(2, $payload['sections']);
        $this->assertCount(2, $payload['sections'][0]['questions']);
        $this->assertCount(2, $payload['sections'][1]['questions']);
        $this->assertSame(60, $payload['settings']['duration_minutes']);
        $this->assertSame(8.0, (float) $payload['settings']['total_points']);
        $this->assertFalse($payload['settings']['shuffle_questions']);

        foreach ($payload['sections'] as $section) {
            foreach ($section['questions'] as $question) {
                $this->assertSame(2.0, (float) $question['points'], 'Điểm mỗi câu phải bằng tổng điểm chia đều số câu.');
            }
        }
    }

    public function test_points_follow_the_requested_structure_even_when_ai_returns_fewer_questions(): void
    {
        $this->fakeExamJson([
            ['title' => 'PHẦN I', 'questions' => [
                $this->question('Câu 1', 'essay'),
            ]],
        ]);

        $payload = $this->artifactOf($this->generateExam([
            'examSections' => 2,
            'examQuestionsPerSection' => 5,
            'examTotalPoints' => 10,
        ]))->payload;

        $question = $payload['sections'][0]['questions'][0];

        $this->assertSame(1.0, (float) $question['points'], 'Điểm câu vẫn theo cấu trúc đã yêu cầu: 10 điểm / 10 câu.');
        $this->assertSame(10.0, (float) $payload['settings']['total_points']);
    }

    public function test_exam_prompt_states_the_structure_and_the_answer_key_rule(): void
    {
        $this->fakeExamJson([
            ['title' => 'PHẦN I', 'questions' => [$this->question('Câu 1', 'essay')]],
        ]);

        $this->generateExam([
            'examSections' => 3,
            'examQuestionsPerSection' => 4,
            'examTotalPoints' => 10,
            'examDurationMinutes' => 45,
        ]);

        Http::assertSent(function (HttpRequest $request): bool {
            $body = $request->data();

            if (! is_array($body)) {
                return false;
            }

            $prompt = implode("\n", array_map(
                fn (array $message): string => (string) ($message['content'] ?? ''),
                $body['messages'] ?? []
            ));

            return str_contains($prompt, '3 phần')
                && str_contains($prompt, 'mỗi phần 4 câu')
                && str_contains($prompt, 'tổng 12 câu')
                && str_contains($prompt, 'đúng 1 đáp án')
                && str_contains($prompt, '45 phút');
        });
    }

    public function test_multiple_correct_options_are_reduced_to_one(): void
    {
        $this->fakeExamJson([
            ['title' => 'PHẦN I', 'questions' => [
                $this->question('Chọn đáp án đúng', 'multiple_choice', [
                    ['content' => 'A', 'is_correct' => true],
                    ['content' => 'B', 'is_correct' => true],
                    ['content' => 'C', 'is_correct' => false],
                ]),
            ]],
        ]);

        $options = $this->artifactOf($this->generateExam())->payload['sections'][0]['questions'][0]['options'];

        $this->assertSame(1, collect($options)->where('is_correct', true)->count());
        $this->assertTrue($options[0]['is_correct']);
        $this->assertFalse($options[1]['is_correct']);
    }

    public function test_question_without_correct_option_becomes_fill_blank(): void
    {
        $this->fakeExamJson([
            ['title' => 'PHẦN I', 'questions' => [
                $this->question('Câu không có đáp án', 'multiple_choice', [
                    ['content' => 'A', 'is_correct' => false],
                    ['content' => 'B', 'is_correct' => false],
                ]),
            ]],
        ]);

        $question = $this->artifactOf($this->generateExam())->payload['sections'][0]['questions'][0];

        $this->assertSame('fill_blank', $question['type']);
        $this->assertSame([], $question['options']);
    }

    public function test_publishing_creates_an_exam_with_duration_shuffle_and_points(): void
    {
        $this->fakeExamJson([
            ['title' => 'PHẦN I', 'instructions' => 'Chọn đáp án đúng', 'questions' => [
                $this->question('Câu 1', 'multiple_choice', [
                    ['content' => 'A', 'is_correct' => true],
                    ['content' => 'B', 'is_correct' => false],
                ]),
                $this->question('Câu 2', 'essay'),
            ]],
        ]);

        $component = $this->generateExam([
            'examSections' => 1,
            'examQuestionsPerSection' => 2,
            'examTotalPoints' => 6,
            'examDurationMinutes' => 90,
            'examShuffleQuestions' => true,
        ]);

        $component->call('publish', $this->artifactOf($component)->id)->assertSet('error', null);

        $exam = Exam::query()->firstOrFail();

        $this->assertSame(ExamType::Exam, $exam->type);
        $this->assertSame('draft', $exam->status->value);
        $this->assertSame(90, $exam->duration_minutes);
        $this->assertTrue($exam->shuffle_questions);
        $this->assertFalse($exam->shuffle_options);
        $this->assertSame(1, ExamSection::query()->where('exam_id', $exam->id)->count());
        $this->assertSame(2, Question::query()->count());
        $this->assertSame(2, $exam->examQuestions()->count());
        $this->assertSame(6.0, (float) $exam->fresh()->total_points);
        $this->assertSame(1, Question::query()
            ->whereHas('options', fn ($query) => $query->where('is_correct', true))
            ->count());
    }

    public function test_publish_and_open_redirects_to_the_exam_builder(): void
    {
        $this->fakeExamJson([
            ['title' => 'PHẦN I', 'questions' => [$this->question('Câu 1', 'essay')]],
        ]);

        $component = $this->generateExam();
        $artifact = $this->artifactOf($component);

        $component->call('publishAndOpen', $artifact->id)->assertSet('error', null);

        $exam = Exam::query()->firstOrFail();
        $artifact->refresh();

        $component->assertRedirect(route('studio.builder', $exam));

        $this->assertSame('published', $artifact->status);
        $this->assertSame($exam->id, $artifact->ref_id);
    }

    public function test_publish_and_open_reopens_the_same_exam_when_already_published(): void
    {
        $this->fakeExamJson([
            ['title' => 'PHẦN I', 'questions' => [$this->question('Câu 1', 'essay')]],
        ]);

        $component = $this->generateExam();
        $artifact = $this->artifactOf($component);

        $component->call('publish', $artifact->id);
        $component->call('publishAndOpen', $artifact->id)
            ->assertSet('error', null)
            ->assertRedirect(route('studio.builder', Exam::query()->firstOrFail()));

        $this->assertSame(1, Exam::query()->count());
    }

    public function test_publish_and_open_rejects_other_artifact_types(): void
    {
        $this->fakeExamJson([
            ['title' => 'PHẦN I', 'questions' => [$this->question('Câu 1', 'essay')]],
        ]);

        $artifact = $this->artifactOf($this->generateExam());
        $artifact->update(['type' => 'document', 'text_content' => 'Tài liệu']);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('openPreview', $artifact->id)
            ->call('publishAndOpen', $artifact->id)
            ->assertSet('error', 'Chỉ đề thi mới mở được trong trình soạn đề.');

        $this->assertSame(0, Exam::query()->count());
    }

    public function test_draft_keeps_exam_settings_and_question_edits(): void
    {
        $this->fakeExamJson([
            ['title' => 'PHẦN I', 'questions' => [
                $this->question('Câu 1', 'multiple_choice', [
                    ['content' => 'A', 'is_correct' => true],
                    ['content' => 'B', 'is_correct' => false],
                ]),
            ]],
        ]);

        $component = $this->generateExam();
        $artifact = $this->artifactOf($component);

        $component->call('openPreview', $artifact->id)
            ->call('startEditingPreview')
            ->set('draftPayload.settings.duration_minutes', 75)
            ->set('draftPayload.settings.shuffle_options', true)
            ->set('draftPayload.sections.0.questions.0.points', 2.5)
            ->call('addExamOption', 0, 0)
            ->set('draftPayload.sections.0.questions.0.options.2.content', 'C')
            ->call('removeExamOption', 0, 0, 1)
            ->call('saveDraft')
            ->assertSet('editingPreview', false);

        $payload = $artifact->fresh()->payload;
        $question = $payload['sections'][0]['questions'][0];

        $this->assertSame(75, $payload['settings']['duration_minutes']);
        $this->assertTrue($payload['settings']['shuffle_options']);
        $this->assertSame(2.5, $question['points']);
        $this->assertSame(['A', 'C'], array_column($question['options'], 'content'));
    }

    public function test_exam_preview_lists_questions_options_and_handoff_button(): void
    {
        $this->fakeExamJson([
            ['title' => 'PHẦN I', 'instructions' => 'Chọn một đáp án đúng nhất', 'questions' => [
                $this->question('Câu hỏi một', 'multiple_choice', [
                    ['content' => 'Đáp án A', 'is_correct' => true],
                    ['content' => 'Đáp án B', 'is_correct' => false],
                ]),
            ]],
        ]);

        $component = $this->generateExam([
            'examSections' => 1,
            'examQuestionsPerSection' => 1,
            'examTotalPoints' => 4,
            'examDurationMinutes' => 45,
        ]);

        $component->call('openPreview', $this->artifactOf($component)->id)
            ->assertSee('Câu 1.', escape: false)
            ->assertSee('Câu hỏi một')
            ->assertSee('Đáp án A')
            ->assertSee('Chọn một đáp án đúng nhất')
            ->assertSee('Xem đáp án')
            ->assertSee('Bảng đáp án', escape: false)
            ->assertSee('Xem trước khi giao')
            ->assertSee('Giao cho học sinh ngay')
            ->assertSee('45 phút')
            ->assertSee('(4 điểm)');
    }

    public function test_exam_preview_warns_when_ai_returns_fewer_questions_than_requested(): void
    {
        $this->fakeExamJson([
            ['title' => 'PHẦN I', 'questions' => [$this->question('Câu hỏi một', 'essay')]],
        ]);

        $component = $this->generateExam([
            'examSections' => 1,
            'examQuestionsPerSection' => 5,
            'examTotalPoints' => 10,
        ]);

        $component->call('openPreview', $this->artifactOf($component)->id)
            ->assertSee('đề đặt 10 điểm', escape: false)
            ->assertSee('1 câu được dùng');
    }

    public function test_points_per_question_is_shared_between_ui_and_generator(): void
    {
        $this->assertSame(2.0, ArtifactGenerator::examPointsPerQuestion(2, 5, 20));
        $this->assertSame(1.0, ArtifactGenerator::examPointsPerQuestion(2, 5, 10));
        $this->assertSame(0.5, ArtifactGenerator::examPointsPerQuestion(4, 5, 10));
        $this->assertSame(10.0, ArtifactGenerator::examPointsPerQuestion(0, 0, 10));
    }

    public function test_exam_artifact_requires_the_exams_feature(): void
    {
        $this->subject->features()->where('feature', 'exams')->update(['is_enabled' => false]);
        $this->subject->forgetFeatureCache();

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('selectType', 'exam')
            ->assertSet('activeType', null)
            ->assertSee('chưa được bật cho môn của bạn');
    }

    public function test_exam_artifact_stores_generation_params_for_regenerate(): void
    {
        $this->fakeExamJson([
            ['title' => 'PHẦN I', 'questions' => [$this->question('Câu 1', 'essay')]],
        ]);

        $component = $this->generateExam([
            'examSections' => 2,
            'examQuestionsPerSection' => 3,
            'examTotalPoints' => 12,
            'examDurationMinutes' => 30,
        ]);

        $artifact = $this->artifactOf($component);
        $generation = $artifact->payload['_generation'];

        $this->assertSame(2, $generation['exam_sections']);
        $this->assertSame(3, $generation['exam_questions_per_section']);
        $this->assertSame(12.0, (float) $generation['exam_total_points']);
        $this->assertSame(30, $generation['exam_duration_minutes']);

        $component->call('regenerate', $artifact->id)->assertSet('error', null);

        $this->assertSame(2.0, (float) $artifact->fresh()->payload['sections'][0]['questions'][0]['points']);
    }

    public function test_exam_title_and_description_reach_the_builder(): void
    {
        $this->fakeExamJson([
            ['title' => 'PHẦN I', 'questions' => [$this->question('Câu 1', 'essay')]],
        ], 'Đề thi giữa kỳ 1');

        $component = $this->generateExam();
        $artifact = $this->artifactOf($component);

        $this->assertSame('Đề do AI soạn', $artifact->payload['description']);
        $this->assertSame('Đề thi giữa kỳ 1', $artifact->title);

        $component->call('publishAndOpen', $artifact->id);

        $exam = Exam::query()->firstOrFail();

        $this->assertSame('Đề thi giữa kỳ 1', $exam->title);
        $this->assertSame('Đề do AI soạn', $exam->description);
    }
}
