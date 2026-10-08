<?php

namespace App\Models;

use App\Enums\AdjustmentReason;
use App\Enums\AdjustmentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail entry for any change made to a trip after it was generated.
 */
class TripAdjustment extends Model
{
    protected $fillable = ['trip_id', 'user_id', 'type', 'reason', 'details', 'note'];

    protected function casts(): array
    {
        return [
            'type' => AdjustmentType::class,
            'reason' => AdjustmentReason::class,
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
