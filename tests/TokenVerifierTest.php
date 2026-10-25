<?php

namespace Finest\Platform\Tests;

use Finest\Platform\Identity\InvalidToken;
use Finest\Platform\Identity\TokenVerifier;
use Finest\Platform\Testing\FakeIdentity;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class TokenVerifierTest extends TestCase
{
    private FakeIdentity $identity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->identity = FakeIdentity::install();
    }

    public function test_a_token_identity_signed_gives_its_claims(): void
    {
        $claims = $this->verify($this->identity->token(['sub' => 'person-1', 'org' => 'org-1']));

        $this->assertSame('person-1', $claims->sub);
        $this->assertSame('org-1', $claims->org);
    }

    public function test_the_keys_are_loaded_once(): void
    {
        $this->verify($this->identity->token(['sub' => 'person-1']));
        $this->verify($this->identity->token(['sub' => 'person-2']));

        Http::assertSentCount(1);
    }

    public function test_an_expired_token_is_invalid(): void
    {
        $this->expectException(InvalidToken::class);
        $this->verify($this->identity->token(['exp' => time() - 60]));
    }

    public function test_a_token_from_a_clock_a_few_seconds_ahead_is_valid(): void
    {
        $ahead = time() + 5;

        $this->assertSame('person-1', $this->verify($this->identity->token(['sub' => 'person-1', 'iat' => $ahead, 'nbf' => $ahead]))->sub);
    }

    public function test_a_token_signed_with_a_key_identity_never_published_is_invalid(): void
    {
        $this->identity->forge('key-1');

        $this->expectException(InvalidToken::class);
        $this->verify($this->identity->token(['sub' => 'person-1']));
    }

    public function test_something_that_is_not_a_jwt_is_invalid(): void
    {
        $this->expectException(InvalidToken::class);
        $this->verify('not-a-token');
    }

    public function test_a_rotated_key_is_picked_up_without_waiting_for_the_cache(): void
    {
        $this->verify($this->identity->token(['sub' => 'person-1']));

        $this->identity->publish('key-2');
        $this->assertSame('person-2', $this->verify($this->identity->token(['sub' => 'person-2'], kid: 'key-2'))->sub);

        Http::assertSent(fn (Request $request) => $request->url() === FakeIdentity::URL.'/.well-known/jwks.json');
        Http::assertSentCount(2);
    }

    private function verify(string $token): object
    {
        return app(TokenVerifier::class)->verify($token);
    }
}
