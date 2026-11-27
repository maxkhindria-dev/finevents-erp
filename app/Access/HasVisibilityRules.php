<?php

namespace App\Access;

use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A model that knows which of its rows an active person may see.
 * Models that don't implement this are fully hidden by the record filter.
 */
interface HasVisibilityRules
{
    /**
     * @param  Builder<covariant Model>  $query
     */
    public function applyVisibility(Builder $query, Person $viewer): void;
}
