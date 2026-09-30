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

/*
| Chạy queue bằng cron trên shared hosting (không có supervisor/daemon):
| cron mỗi phút gọi `schedule:run`, lệnh này xử lý tối đa 100 job rồi dừng,
| phiên sau xử lý tiếp. Không dùng queue:work daemon vì Hostinger kill process.
*/
Schedule::command('queue:work database --stop-when-empty --max-jobs=100 --max-time=50 --sleep=3 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(60)
    ->name('awawa:queue')
    ->onFailure(function (): void {
        logger()->warning('awawa:queue worker thất bại, job sẽ thử lại ở phiên cron sau.');
    });

Schedule::command('queue:prune-failed --hours=168')->weekly()->name('awawa:prune-failed');
