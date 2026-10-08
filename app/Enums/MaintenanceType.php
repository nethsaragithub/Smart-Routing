<?php

namespace App\Enums;

use App\Contracts\HasBadge;
use App\Enums\Concerns\HasOptions;

enum MaintenanceType: string implements HasBadge
{
    use HasOptions;

    case Routine = 'routine';
    case Corrective = 'corrective';

    public function label(): string
    {
        return match ($this) {
            self::Routine => 'Routine',
            self::Corrective => 'Corrective',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Routine => 'blue',
            self::Corrective => 'red',
        };
    }
}
