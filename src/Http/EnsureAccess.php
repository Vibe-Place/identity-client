<?php

namespace Finest\Platform\Http;

use Closure;
use Finest\Platform\Identity\Actor;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Signed in, but without a product this service serves: 403. */
class EnsureAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof Actor && $actor->hasAccess, 403, 'No access to this service.');

        return $next($request);
    }
}
