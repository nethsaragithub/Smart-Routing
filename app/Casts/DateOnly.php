<?php

namespace App\Casts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores a calendar date as "Y-m-d" (Laravel's built-in date cast stores
 * "Y-m-d H:i:s", which breaks range queries on databases that keep the
 * time part, such as SQLite) and reads it back as an immutable Carbon.
 *
 * @implements CastsAttributes<CarbonImmutable, CarbonImmutable|DateTimeInterface|string>
 */
class DateOnly implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value)->startOfDay();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = $value instanceof DateTimeInterface ? CarbonImmutable::instance($value) : CarbonImmutable::parse($value);

        return $date->toDateString();
    }
}
