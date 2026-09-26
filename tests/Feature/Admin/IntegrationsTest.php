<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Integrations;
use App\Models\NotebookSetting;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class IntegrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_save_integration_settings(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        Livewire::test(Integrations::class)
            ->set('tavilyApiKey', 'tvly-secret')
            ->set('aiStream', false)
            ->set('maxPromptChars', 250000)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('tvly-secret', NotebookSetting::get('tavily_api_key'));
        $this->assertSame('0', NotebookSetting::get('ai_stream'));
        $this->assertSame('250000', NotebookSetting::get('notebook_max_prompt_chars'));
    }

    public function test_tavily_key_is_encrypted_at_rest(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        Livewire::test(Integrations::class)->set('tavilyApiKey', 'tvly-secret')->call('save');

        $raw = DB::table('notebook_settings')->where('key', 'tavily_api_key')->value('value');

        $this->assertNotSame('tvly-secret', $raw);
    }

    public function test_teacher_cannot_manage_integrations(): void
    {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teacher($subject)->create();
        $this->actingAs($teacher);

        Livewire::test(Integrations::class)->assertForbidden();
    }
}
