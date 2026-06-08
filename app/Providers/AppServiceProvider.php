<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use App\Models\SystemSetting;
use App\Services\SquarePaymentService;

class AppServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        $this->app->singleton('system.settings', function () {
            return Cache::rememberForever('system_settings', function () {
                return SystemSetting::first();
            });
        });

        $this->app->singleton(SquarePaymentService::class, function ($app) {
            return new SquarePaymentService();
        });
    }


    public function boot(): void
    {

    }

}
