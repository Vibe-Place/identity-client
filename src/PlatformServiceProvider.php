<?php

namespace Finest\Platform;

use Finest\Platform\Console\ConsumeCommand;
use Finest\Platform\Console\RelayCommand;
use Finest\Platform\Events\Nats;
use Finest\Platform\Identity\ActorGuard;
use Finest\Platform\Tenancy\CurrentOrganization;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/platform.php', 'platform');

        $this->app->singleton(Nats::class);
        // One per request or job.
        $this->app->scoped(CurrentOrganization::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // The `platform` auth driver: people and integration keys of an organization.
        Auth::viaRequest('platform', fn ($request) => $this->app->make(ActorGuard::class)($request));

        if ($this->app->runningInConsole()) {
            $this->commands([RelayCommand::class, ConsumeCommand::class]);
            $this->publishes([__DIR__.'/../config/platform.php' => config_path('platform.php')], 'platform-config');
        }
    }
}
