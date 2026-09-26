<x-layouts.app title="API key">
    <div class="space-y-8">
        <x-admin.tabs active="api-keys" />

        <div class="page-head">
            <div>
                <h1 class="page-title">API key</h1>
                <p class="page-sub">Kết nối dịch vụ AI, cấp khóa cho ứng dụng ngoài và tuỳ chọn Notebook.</p>
            </div>
        </div>

        <livewire:admin.ai-providers.index />

        <div class="border-t border-rule pt-8 dark:border-night-700">
            <livewire:admin.api-keys.index />
        </div>

        <div class="border-t border-rule pt-8 dark:border-night-700">
            <livewire:admin.integrations />
        </div>
    </div>
</x-layouts.app>
