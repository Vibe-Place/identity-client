<?php

namespace Finest\Platform\Tests;

use Finest\Platform\PlatformServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RecordingHandler::$handled = [];
        RecordingHandler::$failOn = [];
    }

    protected function getPackageProviders($app): array
    {
        return [PlatformServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('database.default', 'testing');
    }
}
