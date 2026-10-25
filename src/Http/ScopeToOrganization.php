<?php

namespace Finest\Platform\Http;

use Closure;
use Finest\Platform\Identity\Actor;
use Finest\Platform\Tenancy\CurrentOrganization;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The request acts for the actor's organization. Put it before route model
 * binding (`prependToPriorityList(before: SubstituteBindings::class, ...)`), so
 * models are looked up within the organization: another one's record is a 404.
 */
class ScopeToOrganization
{
    public function __construct(private readonly CurrentOrganization $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();
        if ($actor instanceof Actor) {
            $this->current->set($actor->organizationId);
        }

        return $next($request);
    }
}
