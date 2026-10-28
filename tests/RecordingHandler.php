<?php

namespace Finest\Platform\Tests;

use Finest\Platform\Events\Event;
use Finest\Platform\Events\Handler;
use RuntimeException;

/** Notes the ids of the events it handles; throws on the ids in $failOn. */
class RecordingHandler implements Handler
{
    /** @var list<string> */
    public static array $handled = [];

    /** @var list<string> */
    public static array $failOn = [];

    public function handle(Event $event): void
    {
        if (in_array($event->data['id'], self::$failOn, true)) {
            throw new RuntimeException("cannot handle {$event->data['id']}");
        }

        self::$handled[] = $event->data['id'];
    }
}
