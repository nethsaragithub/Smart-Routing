<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum MaintenanceCategory: string
{
    use HasOptions;

    case GeneralService = 'general_service';
    case Engine = 'engine';
    case Brakes = 'brakes';
    case Tyres = 'tyres';
    case Electrical = 'electrical';
    case Transmission = 'transmission';
    case Body = 'body';
    case AirConditioning = 'air_conditioning';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::GeneralService => 'General service',
            self::Engine => 'Engine',
            self::Brakes => 'Brakes',
            self::Tyres => 'Tyres',
            self::Electrical => 'Electrical',
            self::Transmission => 'Gearbox & clutch',
            self::Body => 'Body work',
            self::AirConditioning => 'Air conditioning',
            self::Other => 'Other',
        };
    }
}
