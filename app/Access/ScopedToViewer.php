<?php

namespace App\Access;

/**
 * Every FinEvents model uses this trait and implements HasVisibilityRules
 * (a test enforces both). The trait switches on the automatic record filter.
 */
trait ScopedToViewer
{
    public static function bootScopedToViewer(): void
    {
        static::addGlobalScope(new VisibleToViewerScope);

        // Record who created each row.
        static::creating(function (self $model): void {
            if ($model->recordsCreator() && $model->getAttribute('created_by') === null) {
                $model->setAttribute('created_by', app(AccessContext::class)->viewer()?->id);
            }
        });
    }

    /** Models without a created_by column override this. */
    public function recordsCreator(): bool
    {
        return true;
    }
}
