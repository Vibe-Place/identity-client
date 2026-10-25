<?php

namespace Finest\Platform\Events;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;

/**
 * Hands an event to this service's handlers for its subject (config
 * `platform.events.handlers`), in one transaction with the note that it was
 * handled: an event delivered twice changes nothing the second time.
 */
final class Dispatcher
{
    public function __construct(private readonly Container $container) {}

    /** The subjects this service handles. @return list<string> */
    public function subjects(): array
    {
        return array_keys(config('platform.events.handlers'));
    }

    /** @return bool false when it was handled before */
    public function dispatch(Event $event): bool
    {
        return DB::transaction(function () use ($event) {
            $first = DB::table('handled_events')->insertOrIgnore([
                'id' => $event->id,
                'subject' => $event->subject,
                'handled_at' => now(),
            ]) === 1;
            if (! $first) {
                return false;
            }

            // Subjects have dots: not a config path.
            foreach (config('platform.events.handlers')[$event->subject] ?? [] as $handler) {
                $this->container->make($handler)->handle($event);
            }

            return true;
        });
    }
}
