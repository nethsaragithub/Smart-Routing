<?php

namespace App\Enums\Concerns;

/**
 * Shared helpers for backed enums that expose a label() method.
 */
trait HasOptions
{
    /**
     * Key/value pairs suitable for a <select> element.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
