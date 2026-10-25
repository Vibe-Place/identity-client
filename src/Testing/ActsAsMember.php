<?php

namespace Finest\Platform\Testing;

use Finest\Platform\Identity\Actor;
use Illuminate\Support\Str;

/** For a service's tests: requests made as a member of an organization. */
trait ActsAsMember
{
    /**
     * Acts as an admin of a new organization (or the given one) in the given products.
     *
     * @param  list<string>  $products
     * @return string the organization's id
     */
    protected function actingAsMember(?string $organizationId = null, array $products = ['shop', 'warehouse']): string
    {
        $organizationId ??= Str::lower((string) Str::ulid());
        $this->app['auth']->forgetGuards();
        $this->actingAs(new Actor(
            id: Str::lower((string) Str::ulid()),
            organizationId: $organizationId,
            roles: array_fill_keys($products, 'admin'),
        ), 'platform');

        return $organizationId;
    }
}
