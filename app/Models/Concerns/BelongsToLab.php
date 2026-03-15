<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToLab
{
    /**
     * Boot the BelongsToLab trait.
     */
    protected static function bootBelongsToLab(): void
    {
        static::addGlobalScope('lab', function (Builder $builder) {
            $labId = auth()->user()?->lab_id ?? (app()->bound('current_lab_id') ? app('current_lab_id') : null);
            if ($labId) {
                $builder->where($builder->getModel()->getTable() . '.lab_id', $labId);
            }
        });
    }
}
