<x-layouts.app title="API key">
    <div class="space-y-6">
        <x-admin.tabs active="api-keys" />

        <div class="page-head">
            <div>
                <h1 class="page-title">API key</h1>
                <p class="page-sub">Khóa cho ứng dụng bên ngoài gọi vào awawa, và cấu hình nhà cung cấp AI.</p>
            </div>
        </div>

        <livewire:admin.api-keys.index />
        <livewire:admin.ai-providers.index />
        <livewire:admin.integrations />
    </div>
</x-layouts.app>
