<?php

use App\Models\AiUsageLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Dọn dẹp dữ liệu cũ định kỳ. Cron trên Hostinger chỉ cần chạy:
|   php /path/to/laravel/artisan schedule:run
*/
Schedule::call(function (): void {
    AiUsageLog::query()->where('created_at', '<', now()->subDays(180))->delete();

    DatabaseNotification::query()
        ->whereNotNull('read_at')
        ->where('created_at', '<', now()->subDays(120))
        ->delete();
})->daily()->name('awawa:prune')->withoutOverlapping();

Schedule::command('awawa:due-reminders')->everyFifteenMinutes()->withoutOverlapping();
