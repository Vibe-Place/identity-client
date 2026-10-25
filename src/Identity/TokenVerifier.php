<?php

namespace Finest\Platform\Identity;

use Firebase\JWT\JWT;
use Throwable;

/**
 * Verifies an identity access token (a JWT signed with RS256) with identity's
 * public keys: the signature, and that it is in date, allowing for clocks that
 * drift. Identity is not asked per token. A token naming a key not seen yet
 * reloads the keys once: identity may have rotated them.
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
     */
    public function verify(string $token): object
    {
        $keys = $this->keys->all();
        if (! isset($keys[$this->keyId($token)])) {
            $keys = $this->keys->all(refresh: true);
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

        return is_object($header) ? ($header->kid ?? null) : null;
    }
}
