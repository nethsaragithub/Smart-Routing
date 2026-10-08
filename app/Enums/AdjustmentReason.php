<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AdjustmentReason: string
{
    use HasOptions;

    case Emergency = 'emergency';
    case Breakdown = 'breakdown';
    case Maintenance = 'maintenance';
    case DriverUnavailable = 'driver_unavailable';
    case Traffic = 'traffic';
    case Weather = 'weather';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Emergency => 'Emergency',
            self::Breakdown => 'Vehicle breakdown',
            self::Maintenance => 'Maintenance work',
            self::DriverUnavailable => 'Driver unavailable',
            self::Traffic => 'Traffic congestion',
            self::Weather => 'Weather / road closure',
            self::Other => 'Other',
        };
    }
}
