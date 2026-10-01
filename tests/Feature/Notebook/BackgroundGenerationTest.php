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
use Illuminate\Support\Facades\DB;
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
     * Hàng chờ hỏng (bảng `jobs` thiếu, không kết nối được database…) thì phải rơi
     * về tầng dự phòng thay vì báo lỗi cho giáo viên.
     */
    private function breakAiQueue(): void
    {
        config()->set('queue.connections.ai', ['driver' => 'awawa-het-hang-cho']);
    }

    /**
     * Một phiên `queue:work` ngắn giống hệt cron trên Hostinger: dọn việc đang
     * chờ rồi thoát ngay.
     */
    private function workAiQueue(): void
    {
        $this->artisan('queue:work', [
            'connection' => GenerateArtifact::CONNECTION,
            '--queue' => GenerateArtifact::QUEUE,
            '--stop-when-empty' => true,
            '--sleep' => 1,
            '--tries' => 3,
        ])->assertSuccessful();
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
        $this->assertSame('process', $artifact->payload['_generation_runner']);
        Queue::assertNothingPushed();
    }

    /**
     * Đường chạy được trên Hostinger: chặn `proc_open` nhưng còn cron dọn hàng
     * đời. Xếp hàng để được thử lại thật, và sống được cả khi giáo viên đóng tab
     * ngay sau khi bấm "Tạo".
     */
    public function test_generation_is_queued_when_the_host_blocks_child_processes(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        $this->fakeDocumentText();

        $this->mock(BackgroundProcess::class, function ($mock): void {
            $mock->shouldReceive('phpBinary')->andReturn('/usr/bin/php');
            $mock->shouldReceive('start')->once()->andReturnFalse();
        });

        $this->studio()
            ->call('generate')
            ->assertSet('generating', true)
            ->assertSet('error', null);

        $artifact = NotebookArtifact::query()->firstOrFail();

        $this->assertSame('generating', $artifact->status);
        $this->assertSame('queue', $artifact->payload['_generation_runner']);

        Queue::assertPushedOn(GenerateArtifact::QUEUE, GenerateArtifact::class, function (GenerateArtifact $job) use ($artifact): bool {
            return $job->artifactId === $artifact->id && $job->connection === GenerateArtifact::CONNECTION;
        });

        // AI chỉ chạy ở worker, không chạy trong web request.
        Http::assertNothingSent();
    }

    /**
     * Host không có proc_open lẫn FastCGI (như shared hosting chạy CGI) thì chạy
     * ngay trong request: tab hiện spinner suốt lúc soạn nhưng vẫn ra sản phẩm,
     * thay vì báo lỗi.
     */
    public function test_generation_runs_inside_the_request_when_no_background_option_exists(): void
    {
        $this->breakAiQueue();
        $this->fakeDocumentText('Nội dung soạn ngay trong request.');

        $this->mock(BackgroundProcess::class, function ($mock): void {
            $mock->shouldReceive('phpBinary')->once()->andReturn('/usr/bin/php');
            $mock->shouldReceive('start')->once()->andReturnFalse();
            $mock->shouldReceive('defer')->once()->andReturnFalse();
        });

        $this->studio()
            ->call('generate')
            ->assertSet('generating', false)
            ->assertSet('error', null);

        $artifact = NotebookArtifact::query()->firstOrFail();

        $this->assertSame('draft', $artifact->status);
        $this->assertSame('Nội dung soạn ngay trong request.', $artifact->text_content);
        $this->assertDatabaseCount('jobs', 0);
    }

    /**
     * Hàng chờ hỏng mà vẫn chạy FastCGI thì dùng `defer`: giáo viên phải thấy màn
     * "đang soạn" gần như tức thì, không phải chờ vài chục giây.
     */
    public function test_generation_defers_when_the_host_blocks_child_processes_only(): void
    {
        $this->breakAiQueue();
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

    /**
     * Còn trong hạn treo (30 phút) thì sống, dù là đề thi dài hơi.
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

    public function test_large_exam_is_accepted_and_split_in_background(): void
    {
        Queue::fake();
        $this->fakeDocumentText();

        // 4 phần × 20 câu = 80, vượt ngân sách một lần gọi (72 với
        // NOTEBOOK_MAX_ARTIFACT_TOKENS=12000): vẫn nhận soạn, báo rõ sẽ chia
        // nhiều đợt, AI chỉ chạy ở tiến trình nền.
        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('selectType', ArtifactType::Exam->value)
            ->set('examSections', 4)
            ->set('examQuestionsPerSection', 20)
            ->call('generate')
            ->assertSet('generating', true)
            ->assertSee('nhiều đợt');

        $this->assertSame(1, NotebookArtifact::query()->count());
        Http::assertNothingSent();
    }

    /**
     * Cấu hình sai phải báo ngay khi bấm "Tạo", chứ không được xếp hàng một phút
     * rồi mới đổ lỗi.
     */
    public function test_generation_refuses_an_unusable_provider_right_away(): void
    {
        Queue::fake();
        Http::preventStrayRequests();

        AiProvider::create([
            'key' => 'agnes',
            'label' => 'Agnes AI',
            'base_url' => 'https://apihub.agnes-ai.com/v1',
            'api_key' => null,
            'default_model' => 'agnes-2.0-flash',
            'is_enabled' => true,
            'is_default' => false,
        ]);

        $settings = $this->notebook->settings ?? [];
        $settings['ai_provider'] = 'agnes';
        $settings['ai_model'] = 'agnes-2.0-flash';
        $this->notebook->forceFill(['settings' => $settings])->save();

        $this->studio()
            ->call('generate')
            ->assertSet('generating', false)
            ->assertSee('không khả dụng');

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
     * Lỗi hạ tầng (máy chủ lỗi) thì phải thử lại được: lần sau provider đã tỉnh
     * thì ra nội dung, thay vì bắt giáo viên bấm "Tạo lại" bằng tay.
     */
    public function test_a_transient_failure_is_retried_and_the_next_attempt_succeeds(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::sequence()
                ->push(['error' => ['message' => 'máy chủ bận']], 500)
                ->push([
                    'model' => 'openrouter/free',
                    'choices' => [['message' => ['content' => 'Nội dung soạn ở lần thử sau.']]],
                    'usage' => ['total_tokens' => 20],
                ], 200),
        ]);

        $artifact = $this->queuedArtifact('queue', now());
        dispatch(new GenerateArtifact($artifact->id));

        $this->workAiQueue();

        // Lần đầu hỏng thì artefact phải còn "đang soạn" và việc còn nằm trong
        // hàng đời chờ tới hạn: đó mới là chỗ thử lại có tác dụng.
        $this->assertSame('generating', $artifact->fresh()->status);
        $this->assertDatabaseCount('jobs', 1);

        $waiting = DB::table('jobs')->first();

        $this->assertSame(1, (int) $waiting->attempts);
        $this->assertGreaterThan(now()->getTimestamp(), (int) $waiting->available_at, 'Phải chờ hết backoff mới thử lại.');

        // Cho việc đến hạn rồi chạy phiên cron kế tiếp.
        DB::table('jobs')->update(['available_at' => now()->getTimestamp()]);
        $this->workAiQueue();

        $artifact->refresh();

        $this->assertSame('draft', $artifact->status);
        $this->assertSame('Nội dung soạn ở lần thử sau.', $artifact->text_content);
        $this->assertDatabaseCount('jobs', 0);
        Http::assertSentCount(2);
    }

    /**
     * Lỗi cấu hình (sai thông tin đăng nhập) thử lại cũng hoài: phải dừng ngay,
     * đánh dấu hỏng và đưa lời của provider lên thẻ lỗi.
     */
    public function test_a_configuration_failure_is_not_retried(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response(['error' => ['message' => 'API key không hợp lệ']], 401),
        ]);

        $artifact = $this->queuedArtifact('queue', now());
        dispatch(new GenerateArtifact($artifact->id));

        $this->workAiQueue();
        // Thêm một phiên nữa: việc đã dừng thì không được xếp lại lần nữa.
        $this->workAiQueue();

        $artifact->refresh();

        $this->assertSame('failed', $artifact->status);
        $this->assertStringContainsString('API key không hợp lệ', (string) $artifact->failedReason());
        $this->assertDatabaseCount('jobs', 0);
        Http::assertSentCount(1);
    }

    /**
     * Hai lần bấm "Tạo lại" cho cùng một artefact không được xếp hai việc: khoá
     * chống soạn trùng giữ lại đúng một, nếu không sẽ có hai tiến trình cùng soạn
     * một bản và tốn gấp đôi lượt gọi AI.
     */
    public function test_the_same_artifact_is_never_queued_twice(): void
    {
        Queue::fake();
        $this->fakeDocumentText();

        $this->mock(BackgroundProcess::class, function ($mock): void {
            $mock->shouldReceive('phpBinary')->andReturn('/usr/bin/php');
            $mock->shouldReceive('start')->andReturnFalse();
        });

        $this->studio()->call('generate');

        $artifact = NotebookArtifact::query()->firstOrFail();

        $this->assertSame('queue', $artifact->payload['_generation_runner']);

        // Artefact hỏng nhưng khoá chống soạn trùng vẫn còn giữ: lần bấm "Tạo lại"
        // sau phải bị chặn chứ không xếp thêm việc.
        $artifact->markStalled('Lỗi để kiểm thử.');

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('regenerate', $artifact->id)
            ->assertSet('generating', true);

        Queue::assertPushedTimes(GenerateArtifact::class, 1);
    }

    /**
     * Worker chạy hai tiến trình (cron mỗi phút, một job có thể dài hơn một phút)
     * thì lần thứ hai phải thấy artefact không còn "đang soạn" và bỏ qua, không
     * gọi AI thêm lần nữa.
     */
    public function test_a_double_spawn_does_not_generate_twice(): void
    {
        $this->fakeDocumentText('Chỉ soạn một lần.');

        $artifact = $this->queuedArtifact('queue', now());
        dispatch(new GenerateArtifact($artifact->id));

        $this->workAiQueue();
        $this->workAiQueue();

        $artifact->refresh();

        $this->assertSame('draft', $artifact->status);
        $this->assertSame('Chỉ soạn một lần.', $artifact->text_content);
        $this->assertDatabaseCount('jobs', 0);
        Http::assertSentCount(1);
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

        $artifact = $this->queuedArtifact('respond', now());
        (new GenerateArtifact($artifact->id))->handle(app(ArtifactGenerator::class));

        $this->assertSame('failed', $artifact->fresh()->status);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->assertSee('giới hạn lượt gọi');
    }

    public function test_saving_a_draft_does_not_announce_it_as_finished_again(): void
    {
        $this->fakeDocumentText('Nội dung hoàn tất.');

        $artifact = $this->queuedArtifact('respond', now());
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
     * Cron `schedule:run` không chạy thì job nằm im trong hàng đời mãi: giáo viên
     * chỉ thấy màn "đang soạn". `poll` phải tự soạn nốt thay vì chờ tới hết hạn
     * treo.
     */
    public function test_an_unpicked_queued_generation_is_rescued_by_poll(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        $this->fakeDocumentText('Nội dung cứu khỏi hàng đời.');

        $this->mock(BackgroundProcess::class, function ($mock): void {
            $mock->shouldReceive('phpBinary')->andReturn('/usr/bin/php');
            $mock->shouldReceive('start')->once()->andReturnFalse();
            $mock->shouldReceive('defer')->andReturnFalse();
        });

        $this->studio()->call('generate');

        $artifact = NotebookArtifact::query()->firstOrFail();

        $this->assertSame('queue', $artifact->payload['_generation_runner']);

        $artifact->forceFill(['created_at' => now()->subMinutes(10)])->save();

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])->call('poll');

        $artifact->refresh();

        $this->assertSame('draft', $artifact->status);
        $this->assertSame('Nội dung cứu khỏi hàng đời.', $artifact->text_content);
    }

    /**
     * Mới xếp hàng thì chưa đến nhịp cron đầu tiên: để worker lo, không cứu.
     */
    public function test_a_recently_queued_generation_is_left_for_the_worker(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        $this->fakeDocumentText();

        $this->mock(BackgroundProcess::class, function ($mock): void {
            $mock->shouldReceive('phpBinary')->andReturn('/usr/bin/php');
            $mock->shouldReceive('start')->once()->andReturnFalse();
        });

        $this->studio()->call('generate');

        $artifact = NotebookArtifact::query()->firstOrFail();

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])->call('poll');

        $this->assertSame('generating', $artifact->fresh()->status);
        $this->assertSame('queue', $artifact->fresh()->payload['_generation_runner']);
        $this->assertNull($artifact->fresh()->text_content);

        Http::assertNothingSent();
    }

    /**
     * Worker đã nhận việc thì không xen vào, dù nó còn soạn tới bao lâu nữa.
     */
    public function test_a_claimed_queued_generation_is_never_rescued(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        $this->fakeDocumentText();

        $this->mock(BackgroundProcess::class, function ($mock): void {
            $mock->shouldReceive('phpBinary')->andReturn('/usr/bin/php');
            $mock->shouldReceive('start')->once()->andReturnFalse();
        });

        $this->studio()->call('generate');

        $artifact = NotebookArtifact::query()->firstOrFail();

        $payload = $artifact->payload;
        $payload['_claimed_at'] = now()->timestamp;
        $artifact->forceFill([
            'payload' => $payload,
            'created_at' => now()->subMinutes(10),
        ])->save();

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])->call('poll');

        $this->assertSame('generating', $artifact->fresh()->status);

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
        $queued = $this->queuedArtifact('respond', now()->subMinutes(40));

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
