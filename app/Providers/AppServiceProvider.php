<?php

namespace App\Providers;

use App\Support\SubjectContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SubjectContext::class, fn () => new SubjectContext);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->isProduction()) {
            // Hostinger luôn có SSL: bắt buộc HTTPS cho URL sinh ra.
            URL::forceScheme('https');

            // Chặn migrate:fresh / db:wipe trên môi trường production.
            DB::prohibitDestructiveCommands(true);
        }
    }
}
