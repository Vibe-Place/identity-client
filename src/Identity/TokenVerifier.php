<?php

namespace Finest\Platform\Identity;

use Firebase\JWT\JWT;
use RuntimeException;
use Throwable;

/**
 * Verifies an identity access token (a JWT signed with RS256) with identity's
 * public keys: the signature, and that it is in date, allowing for clocks that
 * drift. Identity is not asked per token. A token naming a key not seen yet
 * reloads the keys (identity may have rotated them), at most once a minute: a
 * stream of tokens with made-up keys costs identity one request. A token naming
 * no key is refused outright.
 *
 * What the claims allow (a role, a scope) is for the service to decide.
 */
final readonly class TokenVerifier
{
    public function __construct(private IdentityKeys $keys) {}

    /**
     * The token's claims.
     *
     * @throws InvalidToken
     * @throws RuntimeException when no keys were ever loaded and identity is out of reach
     */
    public function verify(string $token): object
    {
        $keyId = $this->keyId($token) ?? throw new InvalidToken('The token names no key.');

        $keys = $this->keys->all();
        if (! isset($keys[$keyId]) && $this->keys->refresh()) {
            $keys = $this->keys->all();
        }

        JWT::$leeway = (int) config('platform.identity.leeway');

        try {
            return JWT::decode($token, $keys);
        } catch (Throwable $exception) {
            throw new InvalidToken($exception->getMessage(), previous: $exception);
        }
    }

    /** The `kid` named in the token's header. */
    private function keyId(string $token): ?string
    {
        try {
            $header = JWT::jsonDecode(JWT::urlsafeB64Decode(explode('.', $token)[0]));
        } catch (Throwable $exception) {
            throw new InvalidToken('The token is not a JWT.', previous: $exception);
        }

        $keyId = is_object($header) ? ($header->kid ?? null) : null;

        return is_string($keyId) && $keyId !== '' ? $keyId : null;
    }
}
