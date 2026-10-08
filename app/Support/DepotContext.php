<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Depot;
use App\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;

/**
 * Resolves which depot the current user is working in.
 *
 * Supervisors and staff are locked to their own depot. Administrators can
 * switch between depots; their choice is remembered in the session.
 * Models using the BelongsToDepot trait are automatically filtered by it.
 */
class DepotContext
{
    private const SESSION_KEY = 'current_depot_id';

    /** Cache of the resolved depot, keyed by user id. */
    private ?int $resolvedFor = null;

    private ?Depot $resolved = null;

    private ?int $override = null;

    private bool $overriding = false;

    public function __construct(
        private readonly AuthFactory $auth,
        private readonly Container $container,
    ) {
    }

    public function id(): ?int
    {
        return $this->overriding ? $this->override : $this->depot()?->id;
    }

    public function depot(): ?Depot
    {
        if ($this->overriding) {
            return $this->override ? Depot::find($this->override) : null;
        }

        $user = $this->user();

        if ($user === null) {
            return null; // console, queue or guest: no filtering
        }

        if ($this->resolvedFor === $user->id) {
            return $this->resolved;
        }

        $this->resolvedFor = $user->id;

        if ($user->role !== UserRole::Admin) {
            return $this->resolved = $user->depot;
        }

        $selected = $this->session()?->get(self::SESSION_KEY);

        return $this->resolved = ($selected ? Depot::find($selected) : null)
            ?? $user->depot
            ?? Depot::orderBy('name')->first();
    }

    public function canSwitch(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    public function switchTo(Depot $depot): void
    {
        $this->session()?->put(self::SESSION_KEY, $depot->id);
        $this->resolvedFor = $this->user()?->id;
        $this->resolved = $depot;
    }

    /** @return Collection<int, Depot> */
    public function available(): Collection
    {
        return $this->canSwitch()
            ? Depot::orderBy('name')->get()
            : collect(array_filter([$this->depot()]));
    }

    /**
     * Run a callback against a specific depot (null = all depots). Used by
     * seeders, console commands and tests.
     */
    public function runAs(?int $depotId, callable $callback): mixed
    {
        [$previous, $wasOverriding] = [$this->override, $this->overriding];
        [$this->override, $this->overriding] = [$depotId, true];

        try {
            return $callback();
        } finally {
            [$this->override, $this->overriding] = [$previous, $wasOverriding];
        }
    }

    private function user(): ?User
    {
        $user = $this->auth->guard()->user();

        return $user instanceof User ? $user : null;
    }

    private function session(): ?\Illuminate\Contracts\Session\Session
    {
        $request = $this->container->bound('request') ? $this->container->make('request') : null;

        return $request?->hasSession() ? $request->session() : null;
    }
}
