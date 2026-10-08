<?php

namespace App\Models\Concerns;

use App\Models\Depot;
use App\Support\DepotContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the depot the user is currently working in and fills
 * depot_id automatically when a record is created.
 */
trait BelongsToDepot
{
    public static function bootBelongsToDepot(): void
    {
        static::addGlobalScope('depot', function (Builder $query) {
            $depotId = app(DepotContext::class)->id();

            if ($depotId !== null) {
                $query->where($query->getModel()->qualifyColumn('depot_id'), $depotId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->depot_id)) {
                $model->depot_id = app(DepotContext::class)->id();
            }
        });
    }

    public function depot(): BelongsTo
    {
        return $this->belongsTo(Depot::class);
    }
}
