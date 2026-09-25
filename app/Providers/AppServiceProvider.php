<?php

namespace App\Providers;

use App\Support\SubjectContext;
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
        // Bắt buộc HTTPS ở môi trường production (Hostinger luôn có SSL).
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
