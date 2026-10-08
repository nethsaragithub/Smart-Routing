<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
use App\Services\Scheduling\Rules\BusDoubleBookingRule;
use App\Services\Scheduling\Rules\BusSuitabilityRule;
use App\Services\Scheduling\Rules\DriverDoubleBookingRule;
use App\Services\Scheduling\Rules\DriverEligibilityRule;
use App\Services\Scheduling\Rules\DriverWorkingHoursRule;
use App\Services\Scheduling\Rules\RouteOverlapRule;
use App\Services\Scheduling\Rules\TimetableSanityRule;
use App\Services\Scheduling\ScheduleConflictDetector;
use App\Support\DepotContext;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Conflict rules checked whenever a schedule is created or edited.
     * Add a class here to introduce a new business rule.
     */
    private const SCHEDULE_RULES = [
        TimetableSanityRule::class,
        BusSuitabilityRule::class,
        DriverEligibilityRule::class,
        BusDoubleBookingRule::class,
        DriverDoubleBookingRule::class,
        RouteOverlapRule::class,
        DriverWorkingHoursRule::class,
    ];

    public function register(): void
    {
        $this->app->scoped(DepotContext::class);

        $this->app->bind(ScheduleConflictDetector::class, fn ($app) => new ScheduleConflictDetector(
            array_map(fn (string $rule) => $app->make($rule), self::SCHEDULE_RULES),
        ));
    }

    public function boot(): void
    {
        // Keeps indexes within limits on older MySQL / MariaDB installs (XAMPP, WAMP).
        Schema::defaultStringLength(191);

        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->hasPermission($permission));
        }

        Paginator::defaultView('components.pagination');

        View::composer('components.layouts.app', function ($view) {
            $context = $this->app->make(DepotContext::class);
            $view->with('currentDepot', $context->depot())
                ->with('switchableDepots', $context->canSwitch() ? $context->available() : collect());
        });
    }
}
