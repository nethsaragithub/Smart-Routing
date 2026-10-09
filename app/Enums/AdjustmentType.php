<?php

namespace App\Enums;

use App\Contracts\HasBadge;

enum AdjustmentType: string implements HasBadge
{
    case BusChange = 'bus_change';
    case DriverChange = 'driver_change';
    case Delay = 'delay';
    case Cancellation = 'cancellation';
    case Departure = 'departure';
    case Arrival = 'arrival';
    case Correction = 'correction';

    public function label(): string
    {
        return match ($this) {
            self::Correction => 'Record corrected',
            self::BusChange => 'Bus changed',
            self::DriverChange => 'Driver changed',
            self::Delay => 'Delay reported',
            self::Cancellation => 'Cancelled',
            self::Departure => 'Departed',
            self::Arrival => 'Arrived',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::BusChange, self::DriverChange => 'violet',
            self::Delay => 'amber',
            self::Cancellation => 'red',
            self::Departure => 'green',
            self::Arrival => 'blue',
            self::Correction => 'slate',
        };
    }
}
