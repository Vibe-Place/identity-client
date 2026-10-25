<?php

namespace Finest\Platform\Tenancy;

use LogicException;

/** An organization's records were read or written without knowing for which organization. */
class NoCurrentOrganization extends LogicException
{
    public function __construct()
    {
        parent::__construct('No current organization: set it from the request or in the job first.');
    }
}
