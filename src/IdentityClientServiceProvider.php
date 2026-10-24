<?php

namespace Finest\IdentityClient;

use Illuminate\Support\ServiceProvider;

class IdentityClientServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/identity-client.php', 'identity-client');
    }
}
