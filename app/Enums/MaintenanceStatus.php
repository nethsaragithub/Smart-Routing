<?php

namespace App\Enums;

use App\Contracts\HasBadge;
use App\Enums\Concerns\HasOptions;

enum MaintenanceStatus: string implements HasBadge
{
    use HasOptions;

    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::InProgress => 'In workshop',
            self::Completed => 'Completed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Scheduled => 'slate',
            self::InProgress => 'amber',
            self::Completed => 'green',
        };
    }
}
