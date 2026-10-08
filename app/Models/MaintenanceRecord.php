<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\MaintenanceCategory;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Models\Concerns\BelongsToDepot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceRecord extends Model
{
    use BelongsToDepot, HasFactory;

    protected $fillable = [
        'depot_id', 'bus_id', 'type', 'category', 'title', 'description', 'status', 'scheduled_for',
        'started_on', 'completed_on', 'odometer', 'cost', 'workshop', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => MaintenanceType::class,
            'category' => MaintenanceCategory::class,
            'status' => MaintenanceStatus::class,
            'scheduled_for' => DateOnly::class,
            'started_on' => DateOnly::class,
            'completed_on' => DateOnly::class,
            'odometer' => 'integer',
            'cost' => 'float',
        ];
    }

    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class)->withTrashed();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [MaintenanceStatus::Scheduled, MaintenanceStatus::InProgress]);
    }

    /** Days the bus spent off the road for this job (inclusive). */
    public function downtimeDays(): ?int
    {
        if (! $this->started_on) {
            return null;
        }

        $end = $this->completed_on ?? now();

        return (int) $this->started_on->diffInDays($end) + 1;
    }
}
