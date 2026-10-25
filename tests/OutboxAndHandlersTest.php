<?php

namespace Finest\Platform\Tests;

use Finest\Platform\Events\Event;
use Finest\Platform\Events\Handler;
use Finest\Platform\Events\Outbox;
use Finest\Platform\Testing\InteractsWithEvents;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OutboxAndHandlersTest extends TestCase
{
    use InteractsWithEvents;

    public function test_an_event_is_written_to_the_outbox_with_the_change(): void
    {
        DB::transaction(fn () => app(Outbox::class)->record('catalog.product.saved', ['id' => 'prd_1', 'name' => 'Tile'], 'org-1'));
        try {
            DB::transaction(function () {
                app(Outbox::class)->record('catalog.product.saved', ['id' => 'prd_2']);
                throw new RuntimeException('the change failed');
            });
        } catch (RuntimeException) {
        }

        $event = $this->assertEventRecorded('catalog.product.saved');
        $this->assertSame(['id' => 'prd_1', 'name' => 'Tile'], $event->data);
        $this->assertSame('org-1', $event->organizationId);
        $this->assertCount(1, $this->recordedEvents('catalog.product.saved'));
    }

    public function test_an_event_is_handled_once_however_often_it_comes(): void
    {
        RecordingHandler::$handled = [];
        config(['platform.events.handlers' => ['catalog.product.saved' => [RecordingHandler::class]]]);

        $event = $this->deliverEvent('catalog.product.saved', ['id' => 'prd_1']);
        $this->deliverEvent('catalog.product.saved', ['id' => 'prd_1'], id: $event->id);
        $this->deliverEvent('catalog.product.deleted', ['id' => 'prd_1']);

        $this->assertSame(['prd_1'], RecordingHandler::$handled);
    }

    public function test_a_failed_handler_leaves_the_event_to_come_again(): void
    {
        RecordingHandler::$handled = [];
        config(['platform.events.handlers' => ['catalog.product.saved' => [FailingHandler::class]]]);

        try {
            $this->deliverEvent('catalog.product.saved', ['id' => 'prd_1'], id: '01k0000000000000000000000a');
        } catch (RuntimeException) {
        }

        config(['platform.events.handlers' => ['catalog.product.saved' => [RecordingHandler::class]]]);
        $this->deliverEvent('catalog.product.saved', ['id' => 'prd_1'], id: '01k0000000000000000000000a');
        $this->assertSame(['prd_1'], RecordingHandler::$handled);
    }
}

class RecordingHandler implements Handler
{
    /** @var list<string> */
    public static array $handled = [];

    public function handle(Event $event): void
    {
        self::$handled[] = $event->data['id'];
    }
}

class FailingHandler implements Handler
{
    public function handle(Event $event): void
    {
        throw new RuntimeException('cannot handle');
    }
}
