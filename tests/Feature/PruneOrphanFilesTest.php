<?php

use App\Models\Document;
use App\Models\Notebook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneOrphanFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_livewire_temp_files_older_than_six_hours(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('livewire-tmp/old.jpg', 'old');
        Storage::disk('public')->put('livewire-tmp/fresh.jpg', 'fresh');
        Storage::disk('public')->put('livewire-tmp/old.jpg.json', 'old-meta');

        touch(Storage::disk('public')->path('livewire-tmp/old.jpg'), time() - 7 * 3600);
        touch(Storage::disk('public')->path('livewire-tmp/old.jpg.json'), time() - 7 * 3600);

        $this->artisan('files:prune-orphans')->assertSuccessful();

        Storage::disk('public')->assertMissing('livewire-tmp/old.jpg');
        Storage::disk('public')->assertMissing('livewire-tmp/old.jpg.json');
        Storage::disk('public')->assertExists('livewire-tmp/fresh.jpg');
    }

    public function test_it_deletes_files_of_notebooks_that_no_longer_exist(): void
    {
        Storage::fake('public');

        $kept = Notebook::factory()->create();
        $gone = Notebook::factory()->create();

        Storage::disk('public')->put("notebook/{$kept->id}/a.docx", 'a');
        Storage::disk('public')->put("notebook/{$gone->id}/b.docx", 'b');
        Storage::disk('public')->put('avatars/1/a.jpg', 'a');

        $gone->delete();

        $this->artisan('files:prune-orphans')->assertSuccessful();

        Storage::disk('public')->assertExists("notebook/{$kept->id}/a.docx");
        Storage::disk('public')->assertMissing("notebook/{$gone->id}/b.docx");
        Storage::disk('public')->assertExists('avatars/1/a.jpg');
    }

    public function test_dry_run_reports_without_deleting(): void
    {
        Storage::fake('public');

        $gone = Notebook::factory()->create();
        Storage::disk('public')->put("notebook/{$gone->id}/b.docx", 'b');
        $gone->delete();

        $this->artisan('files:prune-orphans --dry-run')
            ->expectsOutputToContain('dry-run')
            ->assertSuccessful();

        Storage::disk('public')->assertExists("notebook/{$gone->id}/b.docx");
    }

    public function test_it_leaves_document_files_alone(): void
    {
        Storage::fake('public');

        $document = Document::factory()->create(['file_path' => 'documents/a.pdf']);
        Storage::disk('public')->put('documents/a.pdf', 'a');

        $this->artisan('files:prune-orphans')->assertSuccessful();

        Storage::disk('public')->assertExists($document->file_path);
    }
}
