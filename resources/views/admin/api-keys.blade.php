<x-layouts.app title="API key">
    <div class="space-y-6">
        <x-admin.tabs active="api-keys" />

        <header>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">API key</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Quản lý khóa truy cập cho ứng dụng bên ngoài và cấu hình nhà cung cấp AI.
            </p>
        </header>

        <livewire:admin.api-keys.index />
        <livewire:admin.ai-providers.index />
    </div>
</x-layouts.app>
