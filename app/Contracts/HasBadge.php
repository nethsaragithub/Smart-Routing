<?php

namespace App\Contracts;

/**
 * Implemented by enums (and other values) that can be rendered as a coloured
 * status badge in the UI via the <x-badge> Blade component.
 */
interface HasBadge
{
    /** Human readable label, e.g. "On leave". */
    public function label(): string;

    /** Colour tone used by the badge: green, amber, red, blue, slate or violet. */
    public function tone(): string;
}
