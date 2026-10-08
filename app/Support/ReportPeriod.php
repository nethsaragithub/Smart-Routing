<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Immutable date range used by reports and analytics.
 */
final class ReportPeriod
{
    private function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $type,
    ) {
        if ($to->lt($from)) {
            throw new InvalidArgumentException('The end date must be on or after the start date.');
        }
    }

    public static function week(?CarbonInterface $anyDay = null): self
    {
        $day = CarbonImmutable::instance($anyDay ?? now());

        return new self($day->startOfWeek(), $day->endOfWeek()->startOfDay(), 'weekly');
    }

    public static function month(?CarbonInterface $anyDay = null): self
    {
        $day = CarbonImmutable::instance($anyDay ?? now());

        return new self($day->startOfMonth(), $day->endOfMonth()->startOfDay(), 'monthly');
    }

    public static function between(CarbonInterface $from, CarbonInterface $to): self
    {
        return new self(
            CarbonImmutable::instance($from)->startOfDay(),
            CarbonImmutable::instance($to)->startOfDay(),
            'custom',
        );
    }

    public static function lastDays(int $days): self
    {
        return self::between(now()->subDays($days - 1), now());
    }

    /**
     * Build from request input: ?period=weekly|monthly|custom&date=..&from=..&to=..
     */
    public static function fromInput(array $input): self
    {
        $type = $input['period'] ?? 'monthly';
        $date = ! empty($input['date']) ? CarbonImmutable::parse($input['date']) : now();

        return match ($type) {
            'weekly' => self::week($date),
            'custom' => self::between(
                CarbonImmutable::parse($input['from'] ?? now()->startOfMonth()),
                CarbonImmutable::parse($input['to'] ?? now()),
            ),
            default => self::month($date),
        };
    }

    /** @return array{0: string, 1: string} */
    public function dateRange(): array
    {
        return [$this->from->toDateString(), $this->to->toDateString()];
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    /** @return list<CarbonImmutable> */
    public function eachDay(): array
    {
        $days = [];

        for ($d = $this->from; $d->lte($this->to); $d = $d->addDay()) {
            $days[] = $d;
        }

        return $days;
    }

    public function label(): string
    {
        return match ($this->type) {
            'weekly' => 'Week of '.$this->from->format('j M Y'),
            'monthly' => $this->from->format('F Y'),
            default => $this->from->format('j M Y').' to '.$this->to->format('j M Y'),
        };
    }

    public function previous(): self
    {
        return match ($this->type) {
            'weekly' => self::week($this->from->subWeek()),
            'monthly' => self::month($this->from->subMonthNoOverflow()),
            default => self::between($this->from->subDays($this->days()), $this->from->subDay()),
        };
    }

    public function next(): self
    {
        return match ($this->type) {
            'weekly' => self::week($this->from->addWeek()),
            'monthly' => self::month($this->from->addMonthNoOverflow()),
            default => self::between($this->to->addDay(), $this->to->addDays($this->days())),
        };
    }

    /** @return array<string, string> Query parameters that reproduce this period. */
    public function toQuery(): array
    {
        return $this->type === 'custom'
            ? ['period' => 'custom', 'from' => $this->from->toDateString(), 'to' => $this->to->toDateString()]
            : ['period' => $this->type, 'date' => $this->from->toDateString()];
    }
}
