<?php

namespace Finest\Platform\Events;

/**
 * What a service does with an event of another service. It runs in a database
 * transaction, once per event: a redelivered event is not handled again.
 */
interface Handler
{
    public function handle(Event $event): void;
}
