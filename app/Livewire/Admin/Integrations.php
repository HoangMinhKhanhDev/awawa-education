<?php

namespace App\Livewire\Admin;

use App\Models\NotebookSetting;
use App\Services\Notebook\TavilyClient;
use App\Support\NotebookConfig;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Integrations extends Component
{
    public string $tavilyApiKey = '';

    public bool $tavilyConfigured = false;

    public bool $aiStream = true;

    public int $maxPromptChars = 400000;

    public function mount(): void
    {
        $this->guard();

        $this->tavilyConfigured = filled(NotebookConfig::tavilyKey());
        $this->aiStream = NotebookConfig::streamEnabled();
        $this->maxPromptChars = NotebookConfig::maxPromptChars();
    }

    protected function guard(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
    }

    public function save(): void
    {
        $this->guard();

        $this->validate([
            'tavilyApiKey' => ['nullable', 'string', 'max:300'],
            'maxPromptChars' => ['required', 'integer', 'min:10000', 'max:2000000'],
        ], [
            'maxPromptChars.min' => 'Tối thiểu 10.000 ký tự.',
        ]);

        if (filled($this->tavilyApiKey)) {
            NotebookSetting::set('tavily_api_key', $this->tavilyApiKey, true);
        }

        NotebookSetting::set('ai_stream', $this->aiStream ? '1' : '0');
        NotebookSetting::set('notebook_max_prompt_chars', (string) $this->maxPromptChars);

        $this->tavilyApiKey = '';
        $this->tavilyConfigured = filled(NotebookConfig::tavilyKey());

        session()->flash('integrations_status', 'Đã lưu cấu hình tích hợp.');
    }

    public function clearTavily(): void
    {
        $this->guard();

        NotebookSetting::forget('tavily_api_key');
        $this->tavilyConfigured = false;

        session()->flash('integrations_status', 'Đã xóa Tavily API key.');
    }

    public function testTavily(TavilyClient $client): void
    {
        $this->guard();

        try {
            $client->search('kiểm tra kết nối', 1);
            session()->flash('integrations_status', 'Tavily kết nối thành công.');
        } catch (\Throwable $exception) {
            session()->flash('integrations_error', 'Tavily lỗi: '.$exception->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.admin.integrations');
    }
}
