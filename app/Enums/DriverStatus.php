<?php

namespace App\Enums;

use App\Contracts\HasBadge;
use App\Enums\Concerns\HasOptions;

enum DriverStatus: string implements HasBadge
{
    use HasOptions;

    case Active = 'active';
    case OnLeave = 'on_leave';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::OnLeave => 'On leave',
            self::Suspended => 'Suspended',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::OnLeave => 'amber',
            self::Suspended => 'red',
        };
    }
}
