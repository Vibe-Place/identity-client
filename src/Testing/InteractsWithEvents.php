<?php

namespace Finest\Platform\Testing;

use Carbon\CarbonImmutable;
use Finest\Platform\Events\Dispatcher;
use Finest\Platform\Events\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * For a service's tests: what it wrote to its outbox, and an event of another
 * service delivered to its handlers, without NATS.
 */
trait InteractsWithEvents
{
    /** @return list<Event> the events with the subject, oldest first */
    protected function recordedEvents(string $subject): array
    {
        return DB::table('outbox_events')
            ->where('subject', $subject)
            ->orderBy('id')
            ->pluck('event')
            ->map(fn (string $event) => Event::fromArray(json_decode($event, true)))
            ->all();
    }

    /** The latest event with the subject; fails when there is none. */
    protected function assertEventRecorded(string $subject): Event
    {
        $events = $this->recordedEvents($subject);
        $this->assertNotEmpty($events, "No {$subject} event was recorded.");

        return end($events);
    }

    /** @param  array<string, mixed>  $data */
    protected function deliverEvent(string $subject, array $data, ?string $organizationId = null, ?string $id = null): Event
    {
        $event = new Event($id ?? Str::lower((string) Str::ulid()), $subject, $organizationId, CarbonImmutable::now(), $data);
        app(Dispatcher::class)->dispatch($event);

        return $event;
    }
}
