<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardMetrics;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardMetrics $metrics)
    {
    }

    public function index(): View
    {
        $today = CarbonImmutable::today();
        $trips = $this->metrics->tripsOn($today);

        return view('dashboard.index', [
            'today' => $today,
            'trips' => $trips,
            'stats' => $this->metrics->summary($trips),
            'trend' => $this->metrics->lastDays(7),
            'alerts' => $this->metrics->alerts(),
        ]);
    }

    /**
     * Live trip board fragment, polled by the dashboard every 60 seconds.
     */
    public function board(): View
    {
        $today = CarbonImmutable::today();
        $trips = $this->metrics->tripsOn($today);

        return view('dashboard.partials.board', [
            'trips' => $trips,
            'stats' => $this->metrics->summary($trips),
        ]);
    }
}
