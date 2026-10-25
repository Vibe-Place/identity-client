<?php

namespace Finest\Platform\Events;

use Carbon\CarbonImmutable;

/**
 * A fact a service tells the others: «a product was saved». Its subject names
 * the service and what happened (`catalog.product.saved`); its data is what the
 * others need, not the whole record.
 */
final readonly class Event
{
    /**
     * @param  string  $id  a ULID: unique, and ordered in time
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $id,
        public string $subject,
        public ?string $organizationId,
        public CarbonImmutable $occurredAt,
        public array $data,
    ) {}

    /** @param  array{id: string, subject: string, organization_id: ?string, occurred_at: string, data: array<string, mixed>}  $event */
    public static function fromArray(array $event): self
    {
        return new self(
            id: $event['id'],
            subject: $event['subject'],
            organizationId: $event['organization_id'],
            occurredAt: CarbonImmutable::parse($event['occurred_at']),
            data: $event['data'],
        );
    }

    /** @return array{id: string, subject: string, organization_id: ?string, occurred_at: string, data: array<string, mixed>} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'organization_id' => $this->organizationId,
            'occurred_at' => $this->occurredAt->toIso8601ZuluString('microsecond'),
            'data' => $this->data,
        ];
    }
}
