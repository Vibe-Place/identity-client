<?php

namespace Finest\Platform\Events;

/**
 * What a service does with an event of another service. It runs in a database
 * transaction, once per event: a redelivered event is not handled again. When
 * it throws, the event is kept in `failed_events` for events:retry.
 */
interface Handler
{
    public function handle(Event $event): void;
}
