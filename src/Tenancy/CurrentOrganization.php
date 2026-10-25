<?php

namespace Finest\Platform\Tenancy;

/**
 * The organization a request, a job or an event handler acts for: identity's
 * organization id. Queries of an organization's models are scoped to it
 * (BelongsToOrganization). One per request or job (a scoped binding).
 */
final class CurrentOrganization
{
    private ?string $id = null;

    public function set(string $organizationId): void
    {
        $this->id = $organizationId;
    }

    /** Without one, nothing of any organization is read or written. */
    public function id(): string
    {
        return $this->id ?? throw new NoCurrentOrganization;
    }
}
