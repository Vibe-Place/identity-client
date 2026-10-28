<?php

namespace Finest\Platform\Tests;

use Basis\Nats\Client;
use Basis\Nats\Configuration;
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
        $this->requireNats();

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
        $this->requireNats();

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

    public function test_an_event_a_handler_fails_on_is_kept_and_the_consumer_goes_on(): void
    {
        $this->requireNats();
        RecordingHandler::$failOn = ['prd_1'];

        $failing = DB::transaction(fn () => app(Outbox::class)->record('catalog.product.saved', ['id' => 'prd_1']));
        DB::transaction(fn () => app(Outbox::class)->record('catalog.product.saved', ['id' => 'prd_2']));
        app(Relay::class)->publishPending();
        $this->artisan('events:consume', ['--once' => true])->assertSuccessful();

        $this->assertSame(['prd_2'], RecordingHandler::$handled);
        $this->assertSame([$failing->id], DB::table('failed_events')->pluck('id')->all());

        // Acked: it does not come again.
        $this->artisan('events:consume', ['--once' => true])->assertSuccessful();
        $this->assertSame(['prd_2'], RecordingHandler::$handled);

        RecordingHandler::$failOn = [];
        $this->artisan('events:retry')->assertSuccessful();
        $this->assertSame(['prd_2', 'prd_1'], RecordingHandler::$handled);
    }

    public function test_the_stream_keeps_events_for_30_days_and_drops_repeats_within_2_minutes(): void
    {
        $this->requireNats();
        // A stream made before these limits, as on a running platform.
        $url = parse_url(config('platform.nats.url'));
        $stream = (new Client(new Configuration(host: $url['host'], port: $url['port'] ?? 4222)))
            ->getApi()->getStream(config('platform.events.stream'));
        $stream->getConfiguration()->setSubjects([config('platform.events.prefix').'.>']);
        $stream->create();

        DB::transaction(fn () => app(Outbox::class)->record('catalog.product.saved', ['id' => 'prd_1']));
        app(Relay::class)->publishPending();

        $config = $stream->info()->getValues()->config;
        $this->assertSame(30 * 86_400 * 1_000_000_000, $config->max_age);
        $this->assertSame(120 * 1_000_000_000, $config->duplicate_window);
    }

    private function requireNats(): void
    {
        $url = parse_url(config('platform.nats.url'));
        if (! @fsockopen($url['host'], $url['port'] ?? 4222, timeout: 1)) {
            $this->markTestSkipped('No NATS at '.config('platform.nats.url'));
        }
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
