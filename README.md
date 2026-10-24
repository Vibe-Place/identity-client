# finest/identity-client

Finest identity for the platform's Laravel services. The mechanics only, the same
for every service; what a token allows (a role, a scope) is decided by the service.

- `TokenVerifier::verify($token)`: the claims of an identity access token (JWT, RS256),
  checked with identity's public keys from its JWKS endpoint (cached for an hour; a key
  not seen yet reloads them once), allowing for clocks that drift. Identity is not
  asked per request. Throws `InvalidToken`.
- `ServiceToken::for('geo')`: this service's own token for calling another service of
  the platform (client credentials with that service's scope), kept until a minute
  before it expires.
- `Testing\FakeIdentity`: identity in tests over the faked HTTP client: publishes keys,
  signs tokens, issues service tokens.

## Install

```json
"repositories": [{ "type": "vcs", "url": "https://github.com/Vibe-Place/identity-client", "no-api": true }],
"require": { "finest/identity-client": "^0.1" }
```

Environment: `IDENTITY_URL`, and for a service calling others `IDENTITY_CLIENT_ID`,
`IDENTITY_CLIENT_SECRET` (a service client of identity). See `config/identity-client.php`.

A guard of a service:

```php
Auth::viaRequest('identity', function (Request $request) {
    try {
        $claims = app(TokenVerifier::class)->verify((string) $request->bearerToken());
    } catch (Throwable) {
        return null;
    }
    // decide from the claims who this is and whether it may come in
});
```

## Develop

Changes are released as tags (`v0.1.1`); services update with `composer update finest/identity-client`.

```bash
docker run --rm -u "$(id -u)" -e COMPOSER_HOME=/tmp/composer -v "$PWD":/app -w /app --entrypoint bash finest-place/geo \
  -c "composer install && vendor/bin/phpunit && vendor/bin/pint --test"
```
