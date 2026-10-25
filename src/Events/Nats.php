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
 * `finest.>`), kept on disk. Publishing waits for JetStream to store the
 * event; its id is the message id, so a repeated publish is stored once.
 */
final class Nats
{
    private ?Client $client = null;

    public function publish(Event $event): void
    {
        $this->stream()->publish(
            $this->subject($event->subject),
            new Payload(json_encode($event->toArray(), JSON_THROW_ON_ERROR), ['Nats-Msg-Id' => $event->id]),
        );
    }

    /**
     * This service's durable consumer of the given subjects: it starts at the
     * first event of the stream and goes on from where it stopped.
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
            ->setMaxDeliver(10);

        return $consumer->create();
    }

    /** For tests: removes the stream with its events. */
    public function deleteStream(): void
    {
        $this->stream()->delete();
    }

    private function stream(): Stream
    {
        $stream = $this->client()->getApi()->getStream(config('platform.events.stream'));
        $stream->getConfiguration()
            ->setSubjects([config('platform.events.prefix').'.>'])
            ->setStorageBackend(StorageBackend::FILE);

        return $stream->createIfNotExists();
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
