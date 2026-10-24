<?php

namespace Finest\IdentityClient\Tests;

use Finest\IdentityClient\ServiceToken;
use Finest\IdentityClient\Testing\FakeIdentity;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class ServiceTokenTest extends TestCase
{
    public function test_the_token_is_asked_for_with_client_credentials_once_and_reused(): void
    {
        $identity = FakeIdentity::install();

        $this->assertSame('service-token-1', app(ServiceToken::class)->for('geo'));
        $this->assertSame('service-token-1', app(ServiceToken::class)->for('geo'));

        $this->assertSame(1, $identity->issued);
        Http::assertSent(fn (Request $request) => $request->url() === FakeIdentity::URL.'/oauth/token'
            && $request['grant_type'] === 'client_credentials'
            && $request['client_id'] === 'service-client-id'
            && $request['client_secret'] === 'service-secret'
            && $request['scope'] === 'geo');
    }

    public function test_a_fresh_token_replaces_the_kept_one(): void
    {
        FakeIdentity::install();

        (void) app(ServiceToken::class)->for('geo');

        $this->assertSame('service-token-2', app(ServiceToken::class)->for('geo', fresh: true));
        $this->assertSame('service-token-2', app(ServiceToken::class)->for('geo'));
    }

    public function test_without_a_token_from_identity_the_service_is_out_of_reach(): void
    {
        config(['identity-client.url' => FakeIdentity::URL]);
        Http::fake(['identity.test/oauth/token' => Http::response(['error' => 'invalid_client'], 401)]);

        $this->expectException(ConnectionException::class);
        (void) app(ServiceToken::class)->for('geo');
    }
}
