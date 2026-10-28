<?php

namespace Finest\Platform\Tests;

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
        config(['platform.events.handlers' => ['catalog.product.saved' => [RecordingHandler::class]]]);

        $event = $this->deliverEvent('catalog.product.saved', ['id' => 'prd_1']);
        $this->deliverEvent('catalog.product.saved', ['id' => 'prd_1'], id: $event->id);
        $this->deliverEvent('catalog.product.deleted', ['id' => 'prd_1']);

        $this->assertSame(['prd_1'], RecordingHandler::$handled);
    }

    public function test_a_failed_handler_leaves_the_event_to_come_again(): void
    {
        RecordingHandler::$failOn = ['prd_1'];
        config(['platform.events.handlers' => ['catalog.product.saved' => [RecordingHandler::class]]]);

        try {
            $this->deliverEvent('catalog.product.saved', ['id' => 'prd_1'], id: '01k0000000000000000000000a');
        } catch (RuntimeException) {
        }

        RecordingHandler::$failOn = [];
        $this->deliverEvent('catalog.product.saved', ['id' => 'prd_1'], id: '01k0000000000000000000000a');
        $this->assertSame(['prd_1'], RecordingHandler::$handled);
    }
}
