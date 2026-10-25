<?php

namespace Finest\Platform\Tests;

use Finest\Platform\Http\EnsureAccess;
use Finest\Platform\Http\ScopeToOrganization;
use Finest\Platform\Tenancy\CurrentOrganization;
use Finest\Platform\Testing\FakeIdentity;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/** A service serving the warehouse product: who gets in, and for which organization. */
class ActorGuardTest extends TestCase
{
    private FakeIdentity $identity;

    protected function setUp(): void
    {
        parent::setUp();

        config(['platform.identity.products' => ['warehouse'], 'auth.guards.platform' => ['driver' => 'platform']]);
        $this->identity = FakeIdentity::install();
        Route::middleware(['auth:platform', EnsureAccess::class, ScopeToOrganization::class])
            ->get('/whoami', fn () => ['organization' => app(CurrentOrganization::class)->id(), 'role' => request()->user()->role('warehouse')]);
    }

    public function test_a_person_with_a_role_in_a_product_of_the_service_gets_in_for_the_organization(): void
    {
        $this->whoami('org-1', ['products' => ['warehouse'], 'roles' => ['warehouse' => 'storekeeper']])
            ->assertOk()
            ->assertExactJson(['organization' => 'org-1', 'role' => 'storekeeper']);
    }

    public function test_without_the_product_or_a_role_in_it_it_is_403(): void
    {
        $this->whoami('org-1', ['products' => ['logistics'], 'roles' => ['logistics' => 'admin']])->assertForbidden();
        $this->whoami('org-1', ['products' => ['warehouse'], 'roles' => []])->assertForbidden();
    }

    public function test_an_integration_key_needs_the_products_scope(): void
    {
        $key = ['sub' => 'key-1', 'aud' => 'key-1', 'products' => ['warehouse']];

        $this->whoami('org-1', [...$key, 'scopes' => ['warehouse']])->assertOk();
        $this->whoami('org-1', [...$key, 'scopes' => ['logistics']])->assertForbidden();
    }

    public function test_a_token_without_an_organization_or_no_token_is_401(): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($this->identity->token(['sub' => 'service', 'aud' => 'service', 'scopes' => ['geo']]))
            ->getJson('/whoami')->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson('/whoami')->assertUnauthorized();
    }

    /** @param  array<string, mixed>  $claims */
    private function whoami(string $organization, array $claims): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->identity->token(['sub' => 'person-1', 'aud' => 'cabinet', 'org' => $organization, ...$claims]))
            ->getJson('/whoami');
    }
}
