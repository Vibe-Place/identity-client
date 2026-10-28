<?php

namespace Finest\Platform\Identity;

use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * The keys identity signs tokens with, from its JWKS endpoint. Kept until
 * replaced: identity being down does not take them away. They are reloaded
 * when a token names a key not among them, at most once a minute, and a failed
 * reload keeps the ones there are.
 */
final readonly class IdentityKeys
{
    private const string CACHE_KEY = 'platform.identity.jwks';

    private const string REFRESHED_KEY = 'platform.identity.jwks.refreshed';

    /**
     * The kept keys; loaded when there are none yet.
     *
     * @return array<string, Key> keyed by `kid`
     *
     * @throws RuntimeException when there are none and identity gives none
     */
    public function all(): array
    {
        return JWK::parseKeySet(Cache::get(self::CACHE_KEY) ?? $this->load(), 'RS256');
    }

    /**
     * Reloads the keys, unless they were reloaded less than a minute ago.
     *
     * @return bool whether new keys replaced the kept ones
     */
    public function refresh(): bool
    {
        if (! Cache::add(self::REFRESHED_KEY, true, 60)) {
            return false;
        }

        try {
            $this->load();
        } catch (RuntimeException) {
            return false;
        }

        return true;
    }

    /**
     * Loads the keys and keeps them, in place of the ones there were.
     *
     * @return array{keys: list<array<string, string>>}
     */
    private function load(): array
    {
        try {
            $jwks = Http::baseUrl(rtrim(config('platform.identity.url'), '/'))->acceptJson()->timeout(5)
                ->get('.well-known/jwks.json')->throw()->json();
            // Not kept unless they are keys: a broken answer would replace good ones.
            JWK::parseKeySet($jwks, 'RS256');
        } catch (Throwable $exception) {
            throw new RuntimeException('Identity keys are unavailable.', previous: $exception);
        }

        Cache::forever(self::CACHE_KEY, $jwks);

        return $jwks;
    }
}
