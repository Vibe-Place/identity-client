<?php

namespace Finest\Platform\Console;

use Basis\Nats\Message\Payload;
use Finest\Platform\Events\Dispatcher;
use Finest\Platform\Events\Event;
use Finest\Platform\Events\FailedEvents;
use Finest\Platform\Events\Nats;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs under supervisor: other services' events go to this service's handlers.
 * An event a handler throws on is kept in `failed_events` with the error and
 * logged, and the consumer goes on to the next one (events:retry hands it over
 * again). Only when that cannot be kept either (the database is down) does the
 * consumer stop; the event comes again when supervisor starts it anew.
 */
class ConsumeCommand extends Command
{
    protected $signature = 'events:consume {--once : handle what is waiting and stop}';

    protected $description = "Handle other services' events from NATS";

    public function handle(Nats $nats, Dispatcher $dispatcher, FailedEvents $failedEvents): int
    {
        $subjects = $dispatcher->subjects();
        if ($subjects === []) {
            $this->info('No handlers: nothing to consume.');
            // Under supervisor, idle instead of being restarted over and over.
            while (! $this->option('once')) {
                sleep(60);
            }

            return self::SUCCESS;
        }

        // Running: a long poll, one event at a time — NATS hands an event over the
        // moment it arrives, instead of after the wait for a fuller batch. Once: what
        // is waiting, in one batch.
        $once = $this->option('once');
        $nats->consumer(config('platform.events.consumer'), $subjects)
            ->setBatching($once ? 1000 : 1)
            ->setExpires($once ? 1 : 4)
            ->setIterations($once ? 1 : PHP_INT_MAX)
            ->handle(function (Payload $payload) use ($failedEvents) {
                try {
                    $event = Event::fromArray(json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR));
                } catch (Throwable $exception) {
                    // Not an event of the platform: no handler could take it, now or later.
                    Log::error('Not an event, skipped: '.$payload->body, ['exception' => $exception]);

                    return;
                }
                $failedEvents->dispatch($event);
            });

        return self::SUCCESS;
    }
}
