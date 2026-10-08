<?php

namespace App\Enums;

use App\Contracts\HasBadge;
use App\Enums\Concerns\HasOptions;

enum BusStatus: string implements HasBadge
{
    use HasOptions;

    case Active = 'active';
    case Maintenance = 'maintenance';
    case OutOfService = 'out_of_service';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'In service',
            self::Maintenance => 'Under maintenance',
            self::OutOfService => 'Out of service',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Maintenance => 'amber',
            self::OutOfService => 'red',
        };
    }

    public function isOperational(): bool
    {
        return $this === self::Active;
    }
}
