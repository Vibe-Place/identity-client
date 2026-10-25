# finest/platform-client

What every Laravel service of the Finest platform needs, in one package:

- **Identity** (`Finest\Platform\Identity`): `TokenVerifier` verifies identity's access tokens
  with its public keys (cached; reloaded when a new key id shows up), `ServiceToken` gets the
  service's own token for calling another service (client credentials, kept for its hour).
- **Events** (`Finest\Platform\Events`) between services through NATS JetStream, without
  losing or doubling them:
  - `Outbox::record($subject, $data, $organizationId)` writes an event in the same database
    transaction as the change it tells about;
  - `php artisan events:relay` (under supervisor) publishes the outbox, oldest first, to the
    `FINEST` stream (`finest.<service>.<what>`); the event id is the message id, so a repeated
    publish is stored once;
  - `php artisan events:consume` (under supervisor) hands other services' events to this
    service's handlers (`platform.events.handlers`, subject => list of `Handler` classes)
    through its durable consumer; each event is handled once, in a transaction with the note
    that it was (`handled_events`).
- **Organizations kept apart** (`Finest\Platform\Tenancy`): `CurrentOrganization` (identity's
  organization id, one per request or job), the `BelongsToOrganization` model trait, the
  `Owned` validation rule.
- **Prefixed public ids** (`Finest\Platform\Ids\HasPrefixedId`): `ID_PREFIX = 'prd'` → `prd_01k…`.
- **For tests** (`Finest\Platform\Testing`): `FakeIdentity` (keys, tokens, a service token
  endpoint), `InteractsWithEvents` (`assertEventRecorded`, `deliverEvent`).

## Install

```json
"repositories": [{ "type": "vcs", "url": "https://github.com/Vibe-Place/platform-client", "no-api": true }],
"require": { "finest/platform-client": "^0.2" }
```

Environment: `IDENTITY_URL`, `IDENTITY_CLIENT_ID`, `IDENTITY_CLIENT_SECRET` (a service calling
others), `NATS_URL` (default `nats://nats:4222`). The migrations (`outbox_events`,
`handled_events`) load with the package. While the repository is private, composer needs a
read-only GitHub token: `COMPOSER_AUTH='{"github-oauth":{"github.com":"<token>"}}'`.

## Develop

```bash
docker run --rm -u "$(id -u)" --network finestplace_internal -e NATS_URL=nats://nats:4222 \
  -e COMPOSER_HOME=/tmp/composer -v "$PWD":/app -w /app --entrypoint bash finest-place/geo \
  -c "composer install && vendor/bin/phpunit && vendor/bin/pint --test"
```

A change: commit, tag `vX.Y.Z`, push the tag, `composer update finest/platform-client` in
each service.
