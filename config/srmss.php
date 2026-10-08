<?php

/*
|--------------------------------------------------------------------------
| SRMSS operating rules
|--------------------------------------------------------------------------
| Tunable values used by the scheduling and reporting services.
*/

return [

    // Minimum minutes between two departures on the same route.
    'route_headway_minutes' => (int) env('SRMSS_ROUTE_HEADWAY', 10),

    // Turnaround time a bus needs between two trips (cleaning, boarding).
    'bus_turnaround_minutes' => (int) env('SRMSS_BUS_TURNAROUND', 15),

    // Rest a driver needs between two trips.
    'driver_rest_minutes' => (int) env('SRMSS_DRIVER_REST', 30),

    // Minutes late before a departure is reported as delayed.
    'on_time_grace_minutes' => 5,

    // Default diesel price (LKR per litre) pre-filled on the fuel form.
    'default_fuel_price' => (float) env('SRMSS_FUEL_PRICE', 283.00),

    // Drivers using fuel at less than this share of the average economy for
    // their class of bus are flagged for eco-driving coaching (0.88 = 88%).
    'fuel_efficiency_alert_ratio' => 0.88,

];
