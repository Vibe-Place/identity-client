<?php

namespace Finest\Platform\Testing;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;

/**
 * Identity in tests, over the faked HTTP client: it publishes keys at its JWKS
 * endpoint, signs tokens with them and issues service tokens
 * (`service-token-1`, `service-token-2`, ...).
 *
 *     $identity = FakeIdentity::install();
 *     $this->withToken($identity->token(['sub' => ..., 'scopes' => ['geo']]))->getJson(...);
 */
final class FakeIdentity
{
    public const string URL = 'http://identity.test';

    /** Service tokens issued so far. */
    public private(set) int $issued = 0;

    /** False: identity answers 503, as when it is down. */
    public bool $up = true;

    /** @var array<string, string> private keys in PEM, by kid */
    private array $keys = [];

    /** @var list<array<string, string>> the public keys at the JWKS endpoint */
    private array $published = [];

    public static function install(): self
    {
        $identity = new self;

        config([
            'platform.identity.url' => self::URL,
            'platform.identity.client_id' => 'service-client-id',
            'platform.identity.client_secret' => 'service-secret',
        ]);
        Http::fake([
            'identity.test/.well-known/jwks.json' => fn () => $identity->up
                ? Http::response(['keys' => $identity->published])
                : Http::response(status: 503),
            'identity.test/oauth/token' => fn () => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'access_token' => 'service-token-'.++$identity->issued,
            ]),
        ]);
        $identity->publish('key-1');

        return $identity;
    }

    /**
     * A token signed with the key `$kid`, valid for 15 minutes unless the claims
     * say otherwise.
     *
     * @param  array<string, mixed>  $claims
     */
    public function token(array $claims, string $kid = 'key-1'): string
    {
        return JWT::encode([
            'iss' => self::URL,
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + 900,
            ...$claims,
        ], $this->keys[$kid], 'RS256', $kid);
    }

    /** Adds a key to the published ones, as identity does when it rotates keys. */
    public function publish(string $kid): void
    {
        [$private, $jwk] = $this->newKey();
        $this->keys[$kid] = $private;
        $this->published[] = ['kid' => $kid, ...$jwk];
    }

    /** From now on, tokens with `$kid` are signed with a key identity never published. */
    public function forge(string $kid): void
    {
        $this->keys[$kid] = $this->newKey()[0];
    }

    /** @return array{string, array<string, string>} the private key in PEM and the public one as a JWK */
    private function newKey(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        $rsa = openssl_pkey_get_details($key)['rsa'];
        $b64 = fn (string $bytes) => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        return [$private, ['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'n' => $b64($rsa['n']), 'e' => $b64($rsa['e'])]];
    }
}
