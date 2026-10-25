<?php

namespace Finest\Platform\Tenancy;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/**
 * The id of a record of the current organization. Looked up through the
 * model, so its organization scope applies: another organization's id is
 * invalid, as a missing one.
 */
final readonly class Owned implements ValidationRule
{
    /** @param  class-string<Model>  $model */
    public function __construct(private string $model) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->model::query()->whereKey($value)->exists()) {
            $fail('validation.exists')->translate();
        }
    }
}
