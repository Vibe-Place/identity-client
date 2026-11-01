<?php

namespace Finest\Platform\Http;

use Closure;
use Finest\Platform\Identity\Actor;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signed in, but without a product this service serves: 403. With products
 * (`EnsureAccess:warehouse,shop`), routes of those products only: the actor
 * may use one of them.
 */
class EnsureAccess
{
    public function handle(Request $request, Closure $next, string ...$products): Response
    {
        $actor = $request->user();
        $allowed = $actor instanceof Actor && ($products === [] ? $actor->hasAccess : $actor->mayUse(...$products));
        abort_unless($allowed, 403, 'No access to this service.');

        return $next($request);
    }
}
