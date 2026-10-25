<?php

namespace Finest\Platform\Console;

use Basis\Nats\Message\Payload;
use Finest\Platform\Events\Dispatcher;
use Finest\Platform\Events\Event;
use Finest\Platform\Events\Nats;
use Illuminate\Console\Command;

/**
 * Runs under supervisor: other services' events go to this service's handlers.
 * A handler that throws stops the consumer; the event comes again (up to ten
 * times) when supervisor starts it anew.
 */
class ConsumeCommand extends Command
{
    protected $signature = 'events:consume {--once : handle what is waiting and stop}';

    protected $description = "Handle other services' events from NATS";

    public function handle(Nats $nats, Dispatcher $dispatcher): int
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

        // A long poll, one event at a time: NATS hands an event over the moment it
        // arrives, instead of after the wait for a fuller batch.
        $nats->consumer(config('platform.events.consumer'), $subjects)
            ->setBatching(1)
            ->setExpires(4)
            ->setIterations($this->option('once') ? 1 : PHP_INT_MAX)
            ->handle(function (Payload $payload) use ($dispatcher) {
                $dispatcher->dispatch(Event::fromArray(json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR)));
            });

        return self::SUCCESS;
    }
}
