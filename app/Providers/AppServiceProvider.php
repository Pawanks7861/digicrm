<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Integrations\ProviderRegistry::class, function () {
            $registry = new \App\Integrations\ProviderRegistry;
            $registry->register($this->app->make(\App\Integrations\Google\GoogleCalendarProvider::class));
            $registry->register(new \App\Integrations\Google\GoogleAnalyticsProvider);
            $registry->register(new \App\Integrations\Google\GoogleAdsProvider);
            $registry->register(new \App\Integrations\Google\YouTubeAnalyticsProvider);
            $registry->register(new \App\Integrations\Meta\MetaProvider);
            $registry->register(new \App\Integrations\WhatsApp\WhatsAppCloudProvider);

            return $registry;
        });
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        try {
            if (Schema::hasTable('notification_settings')) {
                app(\App\Services\MailConfigService::class)->applyFromSettings();
            }
        } catch (\Throwable) {
            // DB may be unavailable during install/migrate
        }
    }
}
