<?php

namespace Tests\Feature\Notebook;

use App\Enums\ArtifactType;
use App\Jobs\GenerateArtifact;
use App\Livewire\Notebook\Studio;
use App\Models\AiProvider;
use App\Models\Notebook;
use App\Models\NotebookArtifact;
use App\Models\Subject;
use App\Models\User;
use App\Services\Notebook\ArtifactGenerator;
use App\Services\Notebook\SourceIngestor;
use App\Support\BackgroundProcess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class BackgroundGenerationTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private User $teacher;

    private Notebook $notebook;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = Subject::factory()->create();
        $this->subject->features()->where('feature', 'ai_tools')->update(['is_enabled' => true]);
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

    private function fakeDocumentText(string $text = 'Nội dung tài liệu đã soạn.'): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'model' => 'openrouter/free',
                'choices' => [['message' => ['content' => $text]]],
                'usage' => ['total_tokens' => 20],
            ], 200),
        ]);
    }

    private function studio(): Testable
    {
        return Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('selectType', ArtifactType::Document->value);
    }

    /**
     * Việc soạn nội dung chạy ở tiến trình nền nên test phải tự chạy phần đó.
     */
    private function runBackgroundWork(): void
    {
        NotebookArtifact::query()
            ->where('status', 'generating')
            ->orderBy('id')
            ->get()
            ->each(fn (NotebookArtifact $artifact) => (new GenerateArtifact($artifact->id))->handle(app(ArtifactGenerator::class)));
    }

    public function test_generating_leaves_the_artifact_pending_without_queueing_it(): void
    {
        Queue::fake();
        $this->fakeDocumentText();

        $this->studio()->call('generate')->assertSet('generating', true);

        $artifact = NotebookArtifact::query()->firstOrFail();

        $this->assertSame('generating', $artifact->status);
        $this->assertStringContainsString('đang soạn', $artifact->title);
        $this->assertArrayHasKey('_generation', $artifact->payload);

        // Việc soạn chạy ở tiến trình CLI riêng, không đi qua queue.
        Queue::assertNothingPushed();
    }

    public function test_generation_uses_the_scheduler_when_the_host_blocks_every_faster_option(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        $this->fakeDocumentText();

        $this->mock(BackgroundProcess::class, function ($mock): void {
            $mock->shouldReceive('phpBinary')->once()->andReturn('/usr/bin/php');
            $mock->shouldReceive('start')->once()->andReturnFalse();
            $mock->shouldReceive('defer')->once()->andReturnFalse();
        });

        $this->studio()
            ->call('generate')
            ->assertSet('generating', true)
            ->assertSet('error', null)
            ->assertSee('Đã xếp hàng trên máy chủ');

        $artifact = NotebookArtifact::query()->firstOrFail();

        $this->assertSame('generating', $artifact->status);
        $this->assertSame('scheduler', $artifact->payload['_generation_runner']);
        $this->assertStringContainsString('đang chờ soạn nền', $artifact->title);
        Queue::assertNothingPushed();
    }

    /**
     * Đây là đường dùng được trên shared hosting: chặn proc_open nhưng vẫn chạy
     * FastCGI. Giáo viên phải thấy màn "đang soạn" ngay, không phải chờ vài chục giây.
     */
    public function test_generation_defers_when_the_host_blocks_child_processes_only(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        $this->fakeDocumentText();

        $deferred = null;

        $this->mock(BackgroundProcess::class, function ($mock) use (&$deferred): void {
            $mock->shouldReceive('phpBinary')->once()->andReturn('/usr/bin/php');
            $mock->shouldReceive('start')->once()->andReturnFalse();
            $mock->shouldReceive('defer')->once()->andReturnUsing(function (callable $work) use (&$deferred): bool {
                $deferred = $work;

                return true;
            });
        });

        $this->studio()
            ->call('generate')
            ->assertSet('generating', true)
            ->assertSet('error', null);

        $artifact = NotebookArtifact::query()->firstOrFail();

        $this->assertSame('generating', $artifact->status);
        $this->assertSame('respond', $artifact->payload['_generation_runner']);
        $this->assertIsCallable($deferred);

        // Việc soạn chỉ chạy sau khi response đã gửi, nên request phải kết thúc
        // trước khi có kết quả.
        Http::assertNothingSent();

        $deferred();

        $artifact->refresh();

        $this->assertSame('draft', $artifact->status);
        $this->assertSame('Nội dung tài liệu đã soạn.', $artifact->text_content);
    }

    public function test_scheduler_generates_an_artifact_queued_by_the_web_request(): void
    {
        Http::preventStrayRequests();
        $this->fakeDocumentText('Nội dung được soạn từ cron.');

        $this->mock(BackgroundProcess::class, function ($mock): void {
            $mock->shouldReceive('phpBinary')->once()->andReturn('/usr/bin/php');
            $mock->shouldReceive('start')->once()->andReturnFalse();
            $mock->shouldReceive('defer')->once()->andReturnFalse();
        });

        $this->studio()->call('generate');

        $this->artisan('awawa:generate-pending-artifact')->assertSuccessful();

        $artifact = NotebookArtifact::query()->firstOrFail();

        $this->assertSame('draft', $artifact->status);
        $this->assertSame('Nội dung được soạn từ cron.', $artifact->text_content);
        $this->assertArrayNotHasKey('_generation_runner', $artifact->payload);
    }

    /**
     * Giáo viên bấm "Tạo" nhiều lần thì phải không phải chờ từng phút một như trước.
     */
    public function test_one_cron_run_generates_every_queued_artifact(): void
    {
        Http::preventStrayRequests();
        $this->fakeDocumentText('Soạn xong cả loạt.');

        $this->mock(BackgroundProcess::class, function ($mock): void {
            $mock->shouldReceive('phpBinary')->andReturn('/usr/bin/php');
            $mock->shouldReceive('start')->andReturnFalse();
            $mock->shouldReceive('defer')->andReturnFalse();
        });

        $studio = $this->studio();

        $studio->call('generate');
        $studio->call('backToBrowse')->call('selectType', ArtifactType::Document->value)->call('generate');

        $this->assertSame(2, NotebookArtifact::query()->where('status', 'generating')->count());

        $this->artisan('awawa:generate-pending-artifact')->assertSuccessful();

        $this->assertSame(0, NotebookArtifact::query()->where('status', 'generating')->count());
        $this->assertSame(2, NotebookArtifact::query()->where('status', 'draft')->count());
    }

    /**
     * Tiến trình nền chết giữa chừng thì không ai nhặt nữa, nội dung treo vĩnh viễn.
     * Cron phải nhặt lại được, nhưng chỉ sau khi đã quá ngưỡng treo.
     */
    public function test_cron_recovers_an_artifact_whose_background_process_died(): void
    {
        Http::preventStrayRequests();
        $this->fakeDocumentText('Đã cứu được nội dung mồ côi.');

        $artifact = $this->queuedArtifact('process', now()->subMinutes(90));

        $this->artisan('awawa:generate-pending-artifact')->assertSuccessful();

        $artifact->refresh();

        $this->assertSame('draft', $artifact->status);
        $this->assertSame('Đã cứu được nội dung mồ côi.', $artifact->text_content);
    }

    public function test_cron_does_not_steal_an_artifact_that_is_still_running(): void
    {
        Http::preventStrayRequests();
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => 'x']]]])]);

        $artifact = $this->queuedArtifact('process', now()->subMinutes(2));

        $this->artisan('awawa:generate-pending-artifact')->assertSuccessful();

        $this->assertSame('generating', $artifact->fresh()->status);
        Http::assertNothingSent();
    }

    /**
     * Một lần soạn đề thi hợp lệ có thể mất hàng chục phút, nên không được dọn treo
     * bằng cùng ngưỡng ngắn với hàng chờ.
     */
    public function test_a_long_generation_is_not_reported_as_stuck(): void
    {
        Queue::fake();
        $this->fakeDocumentText();

        $this->studio()->call('generate');

        $artifact = NotebookArtifact::query()->firstOrFail();

        NotebookArtifact::query()
            ->whereKey($artifact->id)
            ->update(['updated_at' => now()->subMinutes(20)]);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('poll')
            ->assertSet('generating', true);

        $this->assertSame('generating', $artifact->fresh()->status);
        $this->assertNull($artifact->fresh()->failedReason());
    }

    public function test_generation_is_refused_right_away_when_the_exam_is_too_large(): void
    {
        Queue::fake();
        $this->fakeDocumentText();

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('selectType', ArtifactType::Exam->value)
            ->set('examSections', 6)
            ->set('examQuestionsPerSection', 30)
            ->call('generate')
            ->assertSet('generating', false)
            ->assertSee('vượt giới hạn');

        $this->assertSame(0, NotebookArtifact::query()->count());
        Http::assertNothingSent();
    }

    private function queuedArtifact(string $runner, \DateTimeInterface $updatedAt): NotebookArtifact
    {
        $artifact = NotebookArtifact::create([
            'notebook_id' => $this->notebook->id,
            'subject_id' => $this->subject->id,
            'user_id' => $this->teacher->id,
            'type' => ArtifactType::Document->value,
            'title' => 'Tài liệu đang soạn…',
            'payload' => [
                '_generation' => ['instruction' => 'Tóm tắt nội dung'],
                '_generation_runner' => $runner,
            ],
            'status' => 'generating',
        ]);

        NotebookArtifact::query()->whereKey($artifact->id)->update(['updated_at' => $updatedAt]);

        return $artifact->refresh();
    }

    /**
     * Bị giới hạn thì thẻ lỗi hiện đúng lời của provider ("giới hạn… chờ một
     * chút"), không cần thêm hộp cảnh báo riêng.
     */
    public function test_a_rate_limited_failure_says_limited_and_to_wait_a_bit(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response(['error' => ['message' => 'free tier limit']], 429),
        ]);

        $artifact = $this->queuedArtifact('scheduler', now());
        (new GenerateArtifact($artifact->id))->handle(app(ArtifactGenerator::class));

        $this->assertSame('failed', $artifact->fresh()->status);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->assertSee('giới hạn lượt gọi');
    }

    public function test_saving_a_draft_does_not_announce_it_as_finished_again(): void
    {
        $this->fakeDocumentText('Nội dung hoàn tất.');

        $artifact = $this->queuedArtifact('scheduler', now());
        (new GenerateArtifact($artifact->id))->handle(app(ArtifactGenerator::class));

        $title = $artifact->fresh()->title;

        $component = Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('openPreview', $artifact->id)
            ->call('startEditingPreview')
            ->set('draftTitle', 'Tiêu đề đã sửa')
            ->call('saveDraft');

        $component->assertSee('Tiêu đề đã sửa');

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->assertDontSee('Đã soạn xong: '.$title);
    }

    public function test_the_job_writes_the_result_into_the_artifact(): void
    {
        Queue::fake();
        $this->fakeDocumentText('Nội dung tài liệu đã soạn.');

        $this->studio()->call('generate');

        $artifact = NotebookArtifact::query()->firstOrFail();
        $this->assertSame('generating', $artifact->status);

        (new GenerateArtifact($artifact->id))->handle(app(ArtifactGenerator::class));

        $artifact->refresh();

        $this->assertSame('draft', $artifact->status);
        $this->assertSame('Nội dung tài liệu đã soạn.', $artifact->text_content);
        $this->assertArrayNotHasKey('_error', $artifact->payload);
    }

    /**
     * Nguyên nhân gốc của vòng lặp 30 giây: poll() từng tự gọi AI trong web request.
     * Request poll phải chỉ đọc trạng thái và kết thúc ngay.
     */
    public function test_poll_never_calls_the_ai_from_the_web_request(): void
    {
        Queue::fake();
        $this->fakeDocumentText('Xong rồi mới có nội dung.');

        $this->studio()->call('generate');

        $artifact = NotebookArtifact::query()->firstOrFail();

        NotebookArtifact::query()
            ->whereKey($artifact->id)
            ->update(['updated_at' => now()->subMinutes(1)]);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('poll')
            ->assertSet('generating', true);

        $this->assertSame('generating', $artifact->fresh()->status);
        $this->assertNull($artifact->fresh()->text_content);

        Http::assertNothingSent();
    }

    /**
     * Một ngưỡng treo duy nhất cho mọi trạng thái: còn trong hạn thì sống, quá hạn
     * thì dọn, không phân biệt hàng chờ hay tiến trình đang chạy.
     */
    public function test_a_stuck_artifact_is_reported_as_failed_instead_of_spinning_forever(): void
    {
        Queue::fake();
        $this->fakeDocumentText('Nội dung hoàn tất.');

        $process = $this->queuedArtifact('process', now()->subMinutes(40));
        $queued = $this->queuedArtifact('scheduler', now()->subMinutes(40));

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('poll')
            ->assertSet('generating', false)
            ->assertSee('Tạo lại');

        $this->assertSame('failed', $process->fresh()->status);
        $this->assertSame('failed', $queued->fresh()->status);
        $this->assertStringContainsString('Tạo lại', (string) $process->fresh()->failedReason());
    }

    public function test_a_notice_is_shown_once_after_the_user_comes_back(): void
    {
        Queue::fake();
        $this->fakeDocumentText('Nội dung hoàn tất.');

        $this->studio()->call('generate');

        $artifact = NotebookArtifact::query()->firstOrFail();

        (new GenerateArtifact($artifact->id))->handle(app(ArtifactGenerator::class));

        $title = $artifact->fresh()->title;
        $this->assertStringContainsString('Tài liệu / tóm tắt', $title);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->assertSee('Đã soạn xong: '.$title);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->assertDontSee('Đã soạn xong: '.$title);
    }

    public function test_a_failed_generation_is_recorded_for_retry(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response(['error' => ['message' => 'máy chủ bận']], 500),
        ]);

        $this->studio()->call('generate');

        $artifact = NotebookArtifact::query()->firstOrFail();

        (new GenerateArtifact($artifact->id))->handle(app(ArtifactGenerator::class));

        $artifact->refresh();

        $this->assertSame('failed', $artifact->status);
        $this->assertStringContainsString('máy chủ bận', (string) $artifact->failedReason());
    }

    public function test_publish_and_delete_are_blocked_while_generating(): void
    {
        Queue::fake();
        $this->fakeDocumentText();

        $this->studio()->call('generate');

        $artifact = NotebookArtifact::query()->firstOrFail();
        $this->assertSame('generating', $artifact->status);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('publish', $artifact->id)
            ->assertSet('error', 'Nội dung này đang được soạn, vui lòng đợi xong rồi xuất bản.');

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('delete', $artifact->id)
            ->assertSet('error', 'Nội dung đang được soạn, không thể xóa lúc này.');

        $this->assertDatabaseHas('notebook_artifacts', ['id' => $artifact->id]);
    }

    public function test_regenerate_puts_the_artifact_back_into_background_generation(): void
    {
        $this->fakeDocumentText('Nội dung mới.');

        $this->studio()->call('generate');

        $artifact = NotebookArtifact::query()->firstOrFail();

        (new GenerateArtifact($artifact->id))->handle(app(ArtifactGenerator::class));
        $artifact->refresh();

        Queue::fake();

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('regenerate', $artifact->id)
            ->assertSet('generating', true);

        $artifact->refresh();

        $this->assertSame('generating', $artifact->status);
        $this->assertNull($artifact->text_content);
        $this->assertArrayHasKey('_generation', $artifact->payload);
    }

    public function test_content_can_be_generated_without_any_instruction(): void
    {
        $this->fakeDocumentText('AI tự soạn từ nguồn.');

        app(SourceIngestor::class)->fromText($this->notebook, 'Chuyên đề', 'Nội dung nguồn về bất đẳng thức.');

        $component = Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('selectType', ArtifactType::Document->value)
            ->set('instruction', '')
            ->call('generate')
            ->assertHasNoErrors();

        $artifact = NotebookArtifact::query()->firstOrFail();

        (new GenerateArtifact($artifact->id))->handle(app(ArtifactGenerator::class));

        $artifact->refresh();

        $this->assertSame('draft', $artifact->status);
        $this->assertSame('AI tự soạn từ nguồn.', $artifact->text_content);

        Http::assertSent(function (HttpRequest $request): bool {
            $body = $request->data();

            if (! is_array($body)) {
                return false;
            }

            $userMessage = (string) ($body['messages'][1]['content'] ?? '');

            $this->assertStringContainsString('dựa trên các nguồn đang được bật', $userMessage);
            $this->assertStringNotContainsString('Nhiệm vụ:', $userMessage);

            return true;
        });
    }

    public function test_creating_without_any_source_is_refused(): void
    {
        $this->fakeDocumentText();

        $this->studio()->call('generate')->assertSet('error', null);

        $this->assertSame(1, NotebookArtifact::query()->count());
    }

    public function test_a_new_name_is_used_when_no_instruction_is_given(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'model' => 'openrouter/free',
                'choices' => [['message' => ['content' => 'Nội dung không có tiêu đề trong JSON.']]],
                'usage' => ['total_tokens' => 20],
            ], 200),
        ]);

        app(SourceIngestor::class)->fromText($this->notebook, 'Chuyên đề', 'Nội dung nguồn.');

        $this->studio()->call('generate');

        $artifact = NotebookArtifact::query()->firstOrFail();

        (new GenerateArtifact($artifact->id))->handle(app(ArtifactGenerator::class));

        $this->assertSame('Tài liệu / tóm tắt - '.$this->subject->name, $artifact->fresh()->title);
    }
}
