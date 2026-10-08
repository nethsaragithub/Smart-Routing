<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BusController;
use App\Http\Controllers\BusRouteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepotController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\FuelLogController;
use App\Http\Controllers\MaintenanceRecordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\TimetableController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // Depot Management Dashboard
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/board', [DashboardController::class, 'board'])->name('dashboard.board');
    Route::post('depot/switch', [DepotController::class, 'switch'])->name('depot.switch');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

    // Route Planning
    Route::middleware('can:manage-routes')->group(function () {
        Route::resource('routes', BusRouteController::class)->except(['index', 'show'])->parameters(['routes' => 'route']);
    });
    Route::resource('routes', BusRouteController::class)->only(['index', 'show'])->parameters(['routes' => 'route']);

    // Schedule Management
    Route::get('timetable', [TimetableController::class, 'index'])->name('timetable');
    Route::middleware('can:manage-schedules')->group(function () {
        Route::post('schedules/check', [ScheduleController::class, 'check'])->name('schedules.check');
        Route::get('schedules/options', [ScheduleController::class, 'options'])->name('schedules.options');
        Route::patch('schedules/{schedule}/status', [ScheduleController::class, 'toggleStatus'])->name('schedules.status');
        Route::resource('schedules', ScheduleController::class)->except(['index', 'show']);
    });
    Route::resource('schedules', ScheduleController::class)->only(['index', 'show']);

    // Trips (daily operations)
    Route::get('trips', [TripController::class, 'index'])->name('trips.index');
    Route::get('trips/{trip}', [TripController::class, 'show'])->name('trips.show');
    Route::middleware('can:operate-trips')->group(function () {
        Route::post('trips/generate', [TripController::class, 'generate'])->name('trips.generate');
        Route::post('trips/{trip}/depart', [TripController::class, 'depart'])->name('trips.depart');
        Route::post('trips/{trip}/arrive', [TripController::class, 'arrive'])->name('trips.arrive');
        Route::post('trips/{trip}/delay', [TripController::class, 'delay'])->name('trips.delay');
        Route::post('trips/{trip}/cancel', [TripController::class, 'cancel'])->name('trips.cancel');
        Route::post('trips/{trip}/reassign', [TripController::class, 'reassign'])->name('trips.reassign');
    });

    // Driver and Vehicle Management
    Route::middleware('can:manage-fleet')->group(function () {
        Route::resource('buses', BusController::class)->except(['index', 'show']);
        Route::resource('drivers', DriverController::class)->except(['index', 'show']);
    });
    Route::resource('buses', BusController::class)->only(['index', 'show']);
    Route::resource('drivers', DriverController::class)->only(['index', 'show']);

    // Fuel and Maintenance Log
    Route::get('fuel', [FuelLogController::class, 'index'])->name('fuel.index');
    Route::get('maintenance', [MaintenanceRecordController::class, 'index'])->name('maintenance.index');
    Route::middleware('can:log-fuel-maintenance')->group(function () {
        Route::resource('fuel', FuelLogController::class)->except(['index', 'show'])->parameters(['fuel' => 'fuelLog']);
        Route::resource('maintenance', MaintenanceRecordController::class)->except(['index', 'show'])->parameters(['maintenance' => 'record']);
        Route::post('maintenance/{record}/start', [MaintenanceRecordController::class, 'start'])->name('maintenance.start');
        Route::post('maintenance/{record}/complete', [MaintenanceRecordController::class, 'complete'])->name('maintenance.complete');
    });

    // Reporting and Analytics
    Route::middleware('can:view-reports')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
        Route::get('reports/{report}/export/{format}', [ReportController::class, 'export'])
            ->whereIn('format', ['pdf', 'csv'])->name('reports.export');
    });

    // Administration
    Route::middleware('can:manage-users')->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
    });
    Route::middleware('can:manage-depots')->group(function () {
        Route::resource('depots', DepotController::class)->except(['show', 'destroy']);
    });
});
