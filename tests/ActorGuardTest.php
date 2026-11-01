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

    public function test_routes_of_one_product_let_in_only_those_who_may_use_it(): void
    {
        config(['platform.identity.products' => ['logistics', 'warehouse', 'shop']]);
        Route::middleware(['auth:platform', EnsureAccess::class.':warehouse,shop'])->get('/stock', fn () => 'ok');
        Route::middleware(['auth:platform', EnsureAccess::class.':logistics'])->get('/trips', fn () => 'ok');

        $storekeeper = ['products' => ['logistics', 'warehouse'], 'roles' => ['warehouse' => 'storekeeper']];
        $this->visit('/stock', $storekeeper)->assertOk();
        // The organization uses logistics, but the person has no role in it.
        $this->visit('/trips', $storekeeper)->assertForbidden();
        // A role in logistics, but the organization does not use it.
        $this->visit('/trips', ['products' => ['warehouse'], 'roles' => ['logistics' => 'admin', 'warehouse' => 'admin']])->assertForbidden();

        $key = ['sub' => 'key-1', 'aud' => 'key-1', 'products' => ['logistics', 'shop'], 'scopes' => ['logistics']];
        $this->visit('/trips', $key)->assertOk();
        $this->visit('/stock', $key)->assertForbidden();
    }

    /** @param  array<string, mixed>  $claims */
    private function visit(string $path, array $claims): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->identity->token(['sub' => 'person-1', 'aud' => 'cabinet', 'org' => 'org-1', ...$claims]))
            ->getJson($path);
    }

    /** @param  array<string, mixed>  $claims */
    private function whoami(string $organization, array $claims): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->identity->token(['sub' => 'person-1', 'aud' => 'cabinet', 'org' => $organization, ...$claims]))
            ->getJson('/whoami');
    }
}
