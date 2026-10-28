<?php

namespace Finest\Platform\Tests;

use Carbon\CarbonImmutable;
use Finest\Platform\Events\Event;
use Finest\Platform\Events\FailedEvents;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FailedEventsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('platform.events.handlers', ['catalog.product.saved' => [RecordingHandler::class]]);
    }

    public function test_an_event_a_handler_throws_on_is_kept_with_the_error_and_not_marked_handled(): void
    {
        Log::spy();
        RecordingHandler::$failOn = ['prd_1'];
        $event = $this->event('prd_1');

        $this->assertFalse(app(FailedEvents::class)->dispatch($event));

        $failed = DB::table('failed_events')->sole();
        $this->assertSame($event->id, $failed->id);
        $this->assertSame('catalog.product.saved', $failed->subject);
        $this->assertSame($event->toArray(), json_decode($failed->event, true));
        $this->assertSame(RecordingHandler::class, $failed->handler);
        $this->assertSame('RuntimeException: cannot handle prd_1', $failed->error);
        $this->assertStringContainsString('RecordingHandler.php', $failed->trace);
        $this->assertNotNull($failed->failed_at);
        $this->assertSame(0, DB::table('handled_events')->count());
        Log::shouldHaveReceived('error')->once();
    }

    public function test_retry_hands_the_kept_events_over_again_and_forgets_the_handled_ones(): void
    {
        RecordingHandler::$failOn = ['prd_1', 'prd_2'];
        $first = $this->event('prd_1');
        $second = $this->event('prd_2');
        app(FailedEvents::class)->dispatch($first);
        app(FailedEvents::class)->dispatch($second);

        RecordingHandler::$failOn = ['prd_2'];
        $this->artisan('events:retry')->expectsOutput('Handled: 1. Failed again: 1.')->assertFailed();
        $this->assertSame(['prd_1'], RecordingHandler::$handled);
        $this->assertSame([$second->id], DB::table('failed_events')->pluck('id')->all());
        $this->assertSame([$first->id], DB::table('handled_events')->pluck('id')->all());

        RecordingHandler::$failOn = [];
        $this->artisan('events:retry', ['id' => $second->id])->assertSuccessful();
        $this->assertSame(['prd_1', 'prd_2'], RecordingHandler::$handled);
        $this->assertSame(0, DB::table('failed_events')->count());
    }

    public function test_an_event_failing_again_keeps_its_latest_error(): void
    {
        RecordingHandler::$failOn = ['prd_1'];
        $event = $this->event('prd_1');
        app(FailedEvents::class)->dispatch($event);
        $this->travel(5)->minutes();

        $this->artisan('events:retry', ['id' => $event->id])->assertFailed();

        $this->assertSame(1, DB::table('failed_events')->count());
        $this->assertSame(now()->toDateTimeString(), CarbonImmutable::parse(DB::table('failed_events')->value('failed_at'))->toDateTimeString());
    }

    public function test_retrying_an_event_that_is_not_kept_fails(): void
    {
        $this->artisan('events:retry', ['id' => '01k0000000000000000000000a'])
            ->expectsOutput('No failed event 01k0000000000000000000000a.')
            ->assertFailed();
    }

    private function event(string $id): Event
    {
        return new Event(Str::lower((string) Str::ulid()), 'catalog.product.saved', 'org-1', CarbonImmutable::now(), ['id' => $id]);
    }
}
