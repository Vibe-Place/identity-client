<?php

return [

    // Identity issues the access tokens; its public keys (JWKS) verify them.
    'url' => env('IDENTITY_URL', 'http://identity'),

    // This service as a client of identity, for its own tokens (client credentials).
    'client_id' => env('IDENTITY_CLIENT_ID'),
    'client_secret' => env('IDENTITY_CLIENT_SECRET'),

    // Seconds a token's times may be off: clocks of servers drift.
    'leeway' => 10,

];
