<?php

namespace Finest\Platform\Tenancy;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The id of a record of the current organization. Looked up through the
 * model, so its organization scope applies: another organization's id is
 * invalid, as a missing one. A softly deleted record is invalid too, unless it
 * is one of `$kept`: what the record being edited already refers to.
 */
final readonly class Owned implements ValidationRule
{
    /**
     * @param  class-string<Model>  $model
     * @param  list<string>  $kept
     */
    public function __construct(private string $model, private array $kept = []) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('validation.exists')->translate();

            return;
        }

        $query = $this->model::query()->whereKey($value);
        if (in_array($value, $this->kept, true) && in_array(SoftDeletes::class, class_uses_recursive($this->model), true)) {
            $query->withTrashed();
        }

        if (! $query->exists()) {
            $fail('validation.exists')->translate();
        }
    }
}
