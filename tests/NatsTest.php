<?php

namespace Finest\Platform\Tests;

use Finest\Platform\Events\Nats;
use Finest\Platform\Events\Outbox;
use Finest\Platform\Events\Relay;
use Finest\Platform\Testing\InteractsWithEvents;
use Illuminate\Support\Facades\DB;

/**
 * Through a real NATS (NATS_URL), in a stream of its own. Skipped without one:
 * run the package's tests on the platform's network (see the README).
 */
class NatsTest extends TestCase
{
    use InteractsWithEvents;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('platform.nats.url', getenv('NATS_URL') ?: 'nats://nats:4222');
        $app['config']->set('platform.events.stream', 'KIT_TEST_'.getmypid());
        $app['config']->set('platform.events.prefix', 'kittest'.getmypid());
        $app['config']->set('platform.events.consumer', 'kit-test');
        $app['config']->set('platform.events.handlers', ['catalog.product.saved' => [RecordingHandler::class]]);
    }

    public function test_an_event_goes_from_the_outbox_through_nats_to_a_handler(): void
    {
        $url = parse_url(config('platform.nats.url'));
        if (! @fsockopen($url['host'], $url['port'] ?? 4222, timeout: 1)) {
            $this->markTestSkipped('No NATS at '.config('platform.nats.url'));
        }
        RecordingHandler::$handled = [];

        DB::transaction(fn () => app(Outbox::class)->record('catalog.product.saved', ['id' => 'prd_1']));
        DB::transaction(fn () => app(Outbox::class)->record('catalog.product.saved', ['id' => 'prd_2']));
        $this->assertSame(2, app(Relay::class)->publishPending());
        $this->assertSame(0, app(Relay::class)->publishPending());

        // Published again (as after a crash before marking): JetStream keeps one.
        DB::table('outbox_events')->update(['published_at' => null]);
        app(Relay::class)->publishPending();

        $this->artisan('events:consume', ['--once' => true])->assertSuccessful();
        $this->assertSame(['prd_1', 'prd_2'], RecordingHandler::$handled);
    }

    public function test_a_handler_added_later_gets_the_events_from_then_on(): void
    {
        $url = parse_url(config('platform.nats.url'));
        if (! @fsockopen($url['host'], $url['port'] ?? 4222, timeout: 1)) {
            $this->markTestSkipped('No NATS at '.config('platform.nats.url'));
        }
        RecordingHandler::$handled = [];

        DB::transaction(fn () => app(Outbox::class)->record('catalog.product.saved', ['id' => 'prd_1']));
        app(Relay::class)->publishPending();
        $this->artisan('events:consume', ['--once' => true])->assertSuccessful();

        // A new version of the service handles one more subject: the existing durable
        // consumer takes it on.
        config(['platform.events.handlers' => [
            'catalog.product.saved' => [RecordingHandler::class],
            'logistics.place.saved' => [RecordingHandler::class],
        ]]);
        DB::transaction(fn () => app(Outbox::class)->record('logistics.place.saved', ['id' => 'loc_1']));
        app(Relay::class)->publishPending();
        $this->artisan('events:consume', ['--once' => true])->assertSuccessful();

        $this->assertSame(['prd_1', 'loc_1'], RecordingHandler::$handled);
    }

    protected function tearDown(): void
    {
        try {
            app(Nats::class)->deleteStream();
        } finally {
            parent::tearDown();
        }
    }
}
