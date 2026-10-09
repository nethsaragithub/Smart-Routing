<?php

namespace App\Services\Reports;

use App\Models\Depot;
use App\Support\DepotContext;
use App\Support\ReportPeriod;
use Illuminate\Support\Collection;

/**
 * Base class for every analytics report. Subclasses supply the data; this
 * class provides the shared structure that the web view, the PDF exporter
 * and the CSV exporter all rely on.
 */
abstract class Report
{
    /** @var Collection<int, array<string, mixed>>|null */
    private ?Collection $cachedRows = null;

    public function __construct(protected readonly ReportPeriod $period)
    {
    }

    /** URL slug, e.g. "trip-completion". */
    abstract public static function key(): string;

    abstract public static function title(): string;

    abstract public static function description(): string;

    /** Cost reports for management; supervisors only see the operational ones. */
    public static function forManagement(): bool
    {
        return false;
    }

    /**
     * Column definitions: key => [label, format]. Formats: text, int, dec, pct, lkr.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    abstract public function columns(): array;

    /** @return Collection<int, array<string, mixed>> */
    abstract protected function buildRows(): Collection;

    /**
     * Headline figures shown above the table.
     *
     * @return array<string, string>
     */
    abstract public function summary(): array;

    /**
     * Optional chart definition consumed by the front-end chart component.
     *
     * @return array{type: string, labels: list<string>, datasets: list<array{label: string, data: list<float|int|null>, color: string}>, stacked?: bool, horizontal?: bool, unit?: string}|null
     */
    public function chart(): ?array
    {
        return null;
    }

    /**
     * Plain-language findings to support decision making.
     *
     * @return list<string>
     */
    public function insights(): array
    {
        return [];
    }

    /**
     * Additional tables (e.g. fuel by route).
     *
     * @return list<array{title: string, columns: array<string, array{0: string, 1: string}>, rows: Collection}>
     */
    public function extraTables(): array
    {
        return [];
    }

    /** @return Collection<int, array<string, mixed>> */
    final public function rows(): Collection
    {
        return $this->cachedRows ??= $this->buildRows();
    }

    public function period(): ReportPeriod
    {
        return $this->period;
    }

    public function depot(): ?Depot
    {
        return app(DepotContext::class)->depot();
    }

    public function fileName(string $extension): string
    {
        return sprintf('srmss-%s-%s-to-%s.%s', static::key(), $this->period->from->toDateString(), $this->period->to->toDateString(), $extension);
    }

    /** Format a raw cell value for display. */
    public static function format(mixed $value, string $format): string
    {
        if ($value === null || $value === '') {
            return '–';
        }

        return match ($format) {
            'int' => number_format((float) $value),
            'dec' => number_format((float) $value, 1),
            'dec2' => number_format((float) $value, 2),
            'pct' => number_format((float) $value).'%',
            'lkr' => 'Rs '.number_format((float) $value, 2),
            default => (string) $value,
        };
    }

    protected static function percent(int|float $part, int|float $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }
}
