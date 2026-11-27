<?php

namespace App\Access;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Permission layer 2: applied automatically to every query on a scoped model.
 * Developers cannot forget it; removing it needs an explicit, reviewable call.
 */
class VisibleToViewerScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(AccessContext::class);

        if ($context->isSystem()) {
            return;
        }

        $viewer = $context->viewer();

        // Deny by default: nobody signed in, an inactive person, or a model
        // that has not declared its visibility rules.
        if ($viewer === null || ! $viewer->isActive() || ! $model instanceof HasVisibilityRules) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $model->applyVisibility($builder, $viewer);
    }
}
