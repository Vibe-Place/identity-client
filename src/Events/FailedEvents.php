<?php

namespace Finest\Platform\Events;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Events this service's handlers failed on, kept with the error
 * (`failed_events`): one bad event neither stops the consumer nor is lost.
 * Once the cause is fixed, events:retry hands them to the handlers again.
 */
final class FailedEvents
{
    /** Lines of the trace kept with the error. */
    private const int TRACE_LINES = 20;

    public function __construct(private readonly Dispatcher $dispatcher) {}

    /**
     * Hands the event to the handlers; when they throw, keeps it with the error
     * and logs it.
     *
     * @return bool false when it failed
     */
    public function dispatch(Event $event): bool
    {
        try {
            $this->dispatcher->dispatch($event);
        } catch (Throwable $exception) {
            $this->record($event, $exception);

            return false;
        }

        return true;
    }

    /**
     * Hands the kept events (all, oldest first, or the one) to the handlers
     * again. A handled one is no longer kept; a failed one keeps its new error.
     *
     * @return array{handled: int, failed: int}
     */
    public function retry(?string $id = null): array
    {
        $handled = $failed = 0;
        $rows = DB::table('failed_events')->when($id !== null, fn ($query) => $query->where('id', $id))->lazyById();
        foreach ($rows as $row) {
            if ($this->dispatch(Event::fromArray(json_decode($row->event, true, flags: JSON_THROW_ON_ERROR)))) {
                DB::table('failed_events')->where('id', $row->id)->delete();
                $handled++;
            } else {
                $failed++;
            }
        }

        return ['handled' => $handled, 'failed' => $failed];
    }

    private function record(Event $event, Throwable $exception): void
    {
        $handler = $this->handlerOf($exception);
        $trace = array_slice(explode("\n", $exception->getTraceAsString()), 0, self::TRACE_LINES);

        DB::table('failed_events')->upsert([
            'id' => $event->id,
            'subject' => $event->subject,
            'event' => json_encode($event->toArray(), JSON_THROW_ON_ERROR),
            'handler' => $handler,
            'error' => $exception::class.': '.$exception->getMessage(),
            'trace' => "at {$exception->getFile()}:{$exception->getLine()}\n".implode("\n", $trace),
            'failed_at' => now(),
        ], ['id']);

        Log::error("Event {$event->id} ({$event->subject}) failed in ".($handler ?? 'the dispatcher').'; kept for events:retry.', [
            'exception' => $exception,
        ]);
    }

    /** The handler the exception came out of, from its trace. */
    private function handlerOf(Throwable $exception): ?string
    {
        foreach ($exception->getTrace() as $frame) {
            if ($frame['function'] === 'handle' && is_a($frame['class'] ?? '', Handler::class, true)) {
                return $frame['class'];
            }
        }

        return null;
    }
}
