# finest/platform-client

What every Laravel service of the Finest platform needs, in one package:

- **Identity** (`Finest\Platform\Identity`): `TokenVerifier` verifies identity's access tokens
  with its public keys. The keys are kept until replaced, so identity being down does not lock
  people out; a token naming an unknown key reloads them at most once a minute, a token naming
  no key is refused without asking identity.
- **Events** (`Finest\Platform\Events`) between services through NATS JetStream, without
  losing or doubling them:
  - `Outbox::record($subject, $data, $organizationId)` writes an event in the same database
    transaction as the change it tells about;
  - `php artisan events:relay` (under supervisor) publishes the outbox, oldest first, to the
    `FINEST` stream (`finest.<service>.<what>`, kept 30 days); the event id is the message id,
    so a publish repeated within two minutes is stored once;
  - `php artisan events:consume` (under supervisor) hands other services' events to this
    service's handlers (`platform.events.handlers`, subject => list of `Handler` classes)
    through its durable consumer; each event is handled once, in a transaction with the note
    that it was (`handled_events`);
  - an event a handler throws on does not stop the consumer: it is kept in `failed_events`
    (the event, the handler, the error, a trace excerpt) and logged as an error. Once the cause
    is fixed, `php artisan events:retry` hands them all to the handlers again
    (`events:retry <id>` — one); a handled one is removed, a failed one keeps its new error.
    A retried event may be older than one handled since: handlers should not let it overwrite
    newer data.
- **People and integration keys** (`Finest\Platform\Identity\ActorGuard`, the `platform` auth
  driver): an identity token for an organization; access by the products the service serves
  (`platform.identity.products`). Middleware `Http\EnsureAccess` (403 without access) and
  `Http\ScopeToOrganization` (put it before route model binding).
- **Organizations kept apart** (`Finest\Platform\Tenancy`): `CurrentOrganization` (identity's
  organization id, one per request or job), the `BelongsToOrganization` model trait, the
  `Owned` validation rule.
- **Prefixed public ids** (`Finest\Platform\Ids\HasPrefixedId`): `ID_PREFIX = 'prd'` → `prd_01k…`.
- **For tests** (`Finest\Platform\Testing`): `FakeIdentity` (keys, tokens;
  `$identity->up = false` takes it down), `ActsAsMember` (`actingAsMember()`),
  `InteractsWithEvents` (`assertEventRecorded`, `deliverEvent`).

## Install

```json
"repositories": [{ "type": "vcs", "url": "https://github.com/Vibe-Place/platform-client", "no-api": true }],
"require": { "finest/platform-client": "^0.3" }
```

Environment: `IDENTITY_URL` (its keys), `NATS_URL` (default `nats://nats:4222`). The migrations
(`outbox_events`, `handled_events`, `failed_events`) load with the package. While the repository
is private, composer needs a read-only GitHub token:
`COMPOSER_AUTH='{"github-oauth":{"github.com":"<token>"}}'`.

## Develop

```bash
docker run --rm -u "$(id -u)" --network finestplace_internal -e NATS_URL=nats://nats:4222 \
  -e COMPOSER_HOME=/tmp/composer -v "$PWD":/app -w /app --entrypoint bash finest-place/logistics \
  -c "composer install && vendor/bin/phpunit && vendor/bin/pint --test"
```

A change: commit, tag `vX.Y.Z`, push the tag, `composer update finest/platform-client` in
each service.
