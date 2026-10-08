<?php

namespace App\Enums;

use App\Contracts\HasBadge;
use App\Enums\Concerns\HasOptions;

enum Recurrence: string implements HasBadge
{
    use HasOptions;

    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Daily => 'blue',
            self::Weekly => 'violet',
            self::Monthly => 'slate',
        };
    }
}
