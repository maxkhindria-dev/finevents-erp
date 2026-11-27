<?php

namespace App\Access;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Loading the signed-in person from their session must not itself be filtered
 * by "who is signed in" (that would be circular). Only this provider skips the
 * scope, and only to load the session's own person by ID.
 */
class UnscopedPersonProvider extends EloquentUserProvider
{
    /**
     * @param  Model|null  $model
     * @return Builder<Model>
     */
    protected function newModelQuery($model = null)
    {
        return parent::newModelQuery($model)->withoutGlobalScope(VisibleToViewerScope::class);
    }
}
