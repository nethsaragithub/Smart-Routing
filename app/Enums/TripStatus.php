<?php

namespace App\Enums;

use App\Contracts\HasBadge;
use App\Enums\Concerns\HasOptions;

enum TripStatus: string implements HasBadge
{
    use HasOptions;

    case Scheduled = 'scheduled';
    case OnTime = 'on_time';
    case Delayed = 'delayed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::OnTime => 'On time',
            self::Delayed => 'Delayed',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Scheduled => 'slate',
            self::OnTime => 'green',
            self::Delayed => 'amber',
            self::Completed => 'blue',
            self::Cancelled => 'red',
        };
    }

    /** A trip that has not finished or been cancelled can still be changed. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Scheduled, self::OnTime, self::Delayed], true);
    }

    public function isFinished(): bool
    {
        return ! $this->isOpen();
    }

    /** @return list<self> */
    public static function open(): array
    {
        return [self::Scheduled, self::OnTime, self::Delayed];
    }
}
