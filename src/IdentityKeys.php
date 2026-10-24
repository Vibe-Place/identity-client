<?php

namespace Finest\IdentityClient;

use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The keys identity signs tokens with, from its JWKS endpoint, cached for an
 * hour.
 */
final readonly class IdentityKeys
{
    private const string CACHE_KEY = 'identity-client.jwks';

    /** @return array<string, Key> keyed by `kid` */
    public function all(bool $refresh = false): array
    {
        if ($refresh) {
            Cache::forget(self::CACHE_KEY);
        }

        $jwks = Cache::remember(self::CACHE_KEY, now()->addHour(), function (): array {
            $response = Http::baseUrl(rtrim(config('identity-client.url'), '/'))->acceptJson()->timeout(5)
                ->get('.well-known/jwks.json');
            if ($response->failed()) {
                throw new RuntimeException('Identity keys are unavailable.');
            }

            return $response->json();
        });

        return JWK::parseKeySet($jwks, 'RS256');
    }
}
