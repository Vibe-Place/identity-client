<?php

namespace Finest\Platform\Events;

use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Basis\Nats\Consumer\AckPolicy;
use Basis\Nats\Consumer\Consumer;
use Basis\Nats\Consumer\DeliverPolicy;
use Basis\Nats\Message\Payload;
use Basis\Nats\Stream\StorageBackend;
use Basis\Nats\Stream\Stream;

/**
 * The platform's events in NATS JetStream: one stream (`FINEST`, subjects
 * `finest.>`), kept on disk for 30 days. Publishing waits for JetStream to
 * store the event; its id is the message id, so a repeated publish within two
 * minutes is stored once.
 */
final class Nats
{
    private const int KEEP_DAYS = 30;

    private const int DUPLICATES_WINDOW_SECONDS = 120;

    private ?Client $client = null;

    /** The stream, once this process made sure it exists. */
    private ?Stream $stream = null;

    public function publish(Event $event): void
    {
        $this->stream()->publish(
            $this->subject($event->subject),
            new Payload(json_encode($event->toArray(), JSON_THROW_ON_ERROR), ['Nats-Msg-Id' => $event->id]),
        );
    }

    /**
     * This service's durable consumer of the given subjects: it starts at the
     * first event of the stream and goes on from where it stopped. Its subjects
     * follow the handlers: one added later gets the events from then on, not the
     * ones the consumer has passed.
     *
     * @param  list<string>  $subjects  without the prefix
     */
    public function consumer(string $name, array $subjects): Consumer
    {
        $consumer = $this->stream()->getConsumer($name);
        $consumer->getConfiguration()
            ->setSubjectFilters(array_map($this->subject(...), $subjects))
            ->setDeliverPolicy(DeliverPolicy::ALL)
            ->setAckPolicy(AckPolicy::EXPLICIT)
            // Redelivered only when the consumer died before the ack (a failing handler's
            // event is kept in failed_events and acked): never given up on.
            ->setMaxDeliver(-1);

        // Created, or updated when the subjects changed: the client only creates a
        // missing one, the server's create also updates an existing one.
        $this->client()->api(
            'CONSUMER.DURABLE.CREATE.'.config('platform.events.stream').'.'.$name,
            $consumer->getConfiguration()->toArray(),
        );

        return $consumer->create();
    }

    /** For tests: removes the stream with its events. */
    public function deleteStream(): void
    {
        $this->stream()->delete();
        $this->stream = null;
    }

    /**
     * The stream, created or brought to this configuration once per process:
     * not a round trip to the server on every publish.
     */
    private function stream(): Stream
    {
        if ($this->stream === null) {
            $stream = $this->client()->getApi()->getStream(config('platform.events.stream'));
            $exists = $stream->exists();
            $stream->getConfiguration()
                ->setSubjects([config('platform.events.prefix').'.>'])
                ->setStorageBackend(StorageBackend::FILE)
                ->setMaxAge(self::KEEP_DAYS * 86_400 * 1_000_000_000)
                ->setDuplicateWindow(self::DUPLICATES_WINDOW_SECONDS);
            // An existing stream takes on the limits too: one made before them had none.
            $exists ? $stream->update() : $stream->create();

            $this->stream = $stream;
        }

        return $this->stream;
    }

    private function client(): Client
    {
        if ($this->client === null) {
            $url = parse_url(config('platform.nats.url'));
            $this->client = new Client(new Configuration(host: $url['host'] ?? 'nats', port: $url['port'] ?? 4222, timeout: 5));
        }

        return $this->client;
    }

    private function subject(string $subject): string
    {
        return config('platform.events.prefix').'.'.$subject;
    }
}
