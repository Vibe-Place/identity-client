<?php

namespace Finest\Platform\Console;

use Finest\Platform\Events\Relay;
use Illuminate\Console\Command;

/** Runs under supervisor next to the web server: the outbox goes to NATS as it fills. */
class RelayCommand extends Command
{
    protected $signature = 'events:relay {--once : publish what is waiting and stop}';

    protected $description = 'Publish the outbox to NATS';

    public function handle(Relay $relay): int
    {
        do {
            $published = $relay->publishPending();
            if ($published === 0 && ! $this->option('once')) {
                usleep(200_000);
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }
}
