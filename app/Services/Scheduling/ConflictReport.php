<?php

namespace App\Services\Scheduling;

use Illuminate\Support\Collection;
use JsonSerializable;

/**
 * The outcome of running every conflict rule against a proposal.
 */
final class ConflictReport implements JsonSerializable
{
    /** @var Collection<int, Conflict> */
    private Collection $conflicts;

    /** @param iterable<Conflict> $conflicts */
    public function __construct(iterable $conflicts = [])
    {
        $this->conflicts = collect($conflicts)->values();
    }

    /** @return Collection<int, Conflict> */
    public function all(): Collection
    {
        return $this->conflicts;
    }

    /** @return Collection<int, Conflict> */
    public function errors(): Collection
    {
        return $this->conflicts->filter->isError()->values();
    }

    /** @return Collection<int, Conflict> */
    public function warnings(): Collection
    {
        return $this->conflicts->reject->isError()->values();
    }

    public function hasErrors(): bool
    {
        return $this->errors()->isNotEmpty();
    }

    public function hasWarnings(): bool
    {
        return $this->warnings()->isNotEmpty();
    }

    public function isClear(): bool
    {
        return $this->conflicts->isEmpty();
    }

    public function jsonSerialize(): array
    {
        return [
            'clear' => $this->isClear(),
            'errors' => $this->errors()->all(),
            'warnings' => $this->warnings()->all(),
        ];
    }
}
