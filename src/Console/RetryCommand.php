<?php

namespace Finest\Platform\Console;

use Finest\Platform\Events\FailedEvents;
use Illuminate\Console\Command;

/** After the cause is fixed: the events the handlers failed on go to them again. */
class RetryCommand extends Command
{
    protected $signature = 'events:retry {id? : one failed event; without it, all of them}';

    protected $description = 'Handle the failed events again';

    public function handle(FailedEvents $failedEvents): int
    {
        $id = $this->argument('id');
        ['handled' => $handled, 'failed' => $failed] = $failedEvents->retry($id);
        if ($id !== null && $handled + $failed === 0) {
            $this->error("No failed event {$id}.");

            return self::FAILURE;
        }

        $this->info("Handled: {$handled}. Failed again: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
