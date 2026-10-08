<?php

namespace App\Enums;

use App\Contracts\HasBadge;
use App\Enums\Concerns\HasOptions;

enum ServiceType: string implements HasBadge
{
    use HasOptions;

    case Normal = 'normal';
    case SemiLuxury = 'semi_luxury';
    case Luxury = 'luxury';
    case Express = 'express';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::SemiLuxury => 'Semi-luxury',
            self::Luxury => 'Luxury (A/C)',
            self::Express => 'Expressway',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Normal => 'slate',
            self::SemiLuxury => 'blue',
            self::Luxury => 'violet',
            self::Express => 'green',
        };
    }
}
