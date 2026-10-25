<?php

namespace Finest\Platform\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A record of one organization (`organization_id`). Queries see only the
 * current organization's records, so another organization's record is as good
 * as missing (a 404), and new records belong to the current organization.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $query) {
            $query->where($query->qualifyColumn('organization_id'), app(CurrentOrganization::class)->id());
        });

        static::creating(function (Model $model) {
            $model->organization_id ??= app(CurrentOrganization::class)->id();
        });
    }
}
