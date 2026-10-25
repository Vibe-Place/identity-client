<?php

namespace Finest\Platform\Ids;

use Illuminate\Database\Eloquent\Concerns\HasUniqueStringIds;
use Illuminate\Support\Str;

/**
 * A public id: a ULID prefixed with the record's type, `trp_01jb8x…`. Stored
 * as is (`char(30)`), so the API, logs and the database show the same id.
 * The model names its three-letter prefix in `ID_PREFIX`.
 */
trait HasPrefixedId
{
    use HasUniqueStringIds;

    public function newUniqueId(): string
    {
        return static::ID_PREFIX.'_'.strtolower((string) Str::ulid());
    }

    /** Anything else in a URL is a 404 without a query. */
    protected function isValidUniqueId($value): bool
    {
        $prefix = static::ID_PREFIX.'_';

        return is_string($value) && str_starts_with($value, $prefix) && Str::isUlid(substr($value, strlen($prefix)));
    }
}
