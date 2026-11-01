<?php

namespace Finest\Platform\Identity;

use Illuminate\Http\Request;
use Throwable;

/**
 * The `platform` auth driver: a person's or an integration key's identity token
 * for an organization. Access comes from the products this service serves
 * (`platform.identity.products`): the organization uses one of them, and the
 * person has a role in it or the key has its scope. The actor also knows every
 * product it may use, for routes of one product (`EnsureAccess:logistics`).
 */
final readonly class ActorGuard
{
    public function __construct(private TokenVerifier $tokens) {}

    /** The actor of a valid token for an organization, or null (401). */
    public function __invoke(Request $request): ?Actor
    {
        try {
            $claims = $this->tokens->verify((string) $request->bearerToken());
        } catch (Throwable) {
            return null;
        }
        if (! isset($claims->org, $claims->sub)) {
            return null;
        }

        $roles = (array) ($claims->roles ?? []);
        // An integration key's token is issued to the key itself: sub = aud.
        $integration = $claims->sub === ($claims->aud ?? null);
        $products = array_values(array_filter(
            (array) ($claims->products ?? []),
            fn (string $product) => $integration ? in_array($product, (array) ($claims->scopes ?? []), true) : isset($roles[$product]),
        ));
        $hasAccess = array_intersect(config('platform.identity.products'), $products) !== [];

        return new Actor($claims->sub, $claims->org, $roles, $integration, $hasAccess, $products);
    }
}
