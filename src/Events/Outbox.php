<?php

namespace Finest\Platform\Events;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Where a service writes its events: in the same database transaction as the
 * change they tell about. Either both are saved or neither; events:relay
 * publishes them afterwards, in order.
 */
final class Outbox
{
    /** @param  array<string, mixed>  $data */
    public function record(string $subject, array $data, ?string $organizationId = null): Event
    {
        $event = new Event(
            id: Str::lower((string) Str::ulid()),
            subject: $subject,
            organizationId: $organizationId,
            occurredAt: CarbonImmutable::now(),
            data: $data,
        );

        DB::table('outbox_events')->insert([
            'id' => $event->id,
            'subject' => $subject,
            'event' => json_encode($event->toArray(), JSON_THROW_ON_ERROR),
            'created_at' => $event->occurredAt,
        ]);

        return $event;
    }
}
