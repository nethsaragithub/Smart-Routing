<?php

namespace App\Services\Reports;

/**
 * Colours for charts. Categorical slots are assigned in this fixed order
 * (validated for colour-vision deficiency); status colours are reserved for
 * trip outcomes and never reused for ordinary series.
 */
final class ChartPalette
{
    public const SERIES = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100'];

    public const ON_TIME = '#2e8540';

    public const LATE = '#d18a00';

    public const CANCELLED = '#c23a35';

    public static function series(int $index): string
    {
        return self::SERIES[$index] ?? self::SERIES[0];
    }
}
