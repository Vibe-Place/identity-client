<?php

return [

    'identity' => [
        // Identity issues the access tokens; its public keys (JWKS) verify them.
        'url' => env('IDENTITY_URL', 'http://identity'),

        // This service as a client of identity, for its own tokens (client credentials).
        'client_id' => env('IDENTITY_CLIENT_ID'),
        'client_secret' => env('IDENTITY_CLIENT_SECRET'),

        // Seconds a token's times may be off: clocks of servers drift.
        'leeway' => 10,

        // People and integration keys get in with any of these products (the `platform`
        // auth driver): ['warehouse'], or ['warehouse', 'shop'] for a shared service.
        'products' => [],
    ],

    /*
    | NATS with JetStream: where events travel between the platform's services.
    */
    'nats' => [
        'url' => env('NATS_URL', 'nats://nats:4222'),
    ],

    'events' => [
        // One stream holds every event of the platform, subjects `finest.<service>.<what>`.
        'stream' => 'FINEST',
        'prefix' => 'finest',

        // This service's durable consumer: it gets each event once, from where it stopped.
        'consumer' => env('EVENTS_CONSUMER', env('APP_NAME', 'service')),

        /*
        | What this service does with other services' events, by subject (without
        | the prefix): ['catalog.product.saved' => [SaveProductCopy::class]].
        | A handler implements Finest\Platform\Events\Handler.
        */
        'handlers' => [],
    ],

];
