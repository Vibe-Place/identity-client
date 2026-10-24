<?php

namespace Finest\IdentityClient;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * This service's own access token from identity (client credentials), for
 * calling the platform's other services. Kept until a minute before it
 * expires, so identity is asked about once an hour, not per call.
 */
final readonly class ServiceToken
{
    /**
     * A token with the scope of the service to call (`geo`).
     *
     * @throws ConnectionException when identity issues none: to the caller,
     *                             the service it wanted is out of reach
     */
    #[\NoDiscard]
    public function for(string $scope, bool $fresh = false): string
    {
        $key = "identity-client.service-token.{$scope}";
        if ($fresh) {
            Cache::forget($key);
        }

        $token = Cache::get($key);
        if ($token) {
            return $token;
        }

        $response = Http::baseUrl(rtrim(config('identity-client.url'), '/'))->acceptJson()->asForm()->timeout(5)
            ->post('oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => config('identity-client.client_id'),
                'client_secret' => config('identity-client.client_secret'),
                'scope' => $scope,
            ]);
        if ($response->failed()) {
            throw new ConnectionException("Identity issued no token for [{$scope}]: HTTP {$response->status()}.");
        }

        $token = $response->json('access_token');
        Cache::put($key, $token, max(60, $response->json('expires_in') - 60));

        return $token;
    }
}
