<?php

namespace Finest\Platform\Events;

use Illuminate\Support\Facades\DB;

/**
 * Publishes the outbox to NATS, oldest first. A row is marked published only
 * after JetStream stored the event; publishing it again after a crash stores
 * it once (the message id).
 */
final class Relay
{
    public function __construct(private readonly Nats $nats) {}

    /** @return int how many events were published */
    public function publishPending(int $limit = 100): int
    {
        return DB::transaction(function () use ($limit) {
            $rows = DB::table('outbox_events')
                ->whereNull('published_at')
                ->orderBy('id')
                ->limit($limit)
                // A second relay takes the next rows instead of waiting (PostgreSQL).
                ->lock('for update skip locked')
                ->get();

            foreach ($rows as $row) {
                $this->nats->publish(Event::fromArray(json_decode($row->event, true, flags: JSON_THROW_ON_ERROR)));
                DB::table('outbox_events')->where('id', $row->id)->update(['published_at' => now()]);
            }

            return $rows->count();
        });
    }
}
