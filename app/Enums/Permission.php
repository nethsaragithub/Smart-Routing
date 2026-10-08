<?php

namespace App\Enums;

/**
 * Fine-grained abilities. Each case is registered as a Gate in
 * AppServiceProvider, so views and routes can use @can('manage-routes').
 */
enum Permission: string
{
    case ManageUsers = 'manage-users';
    case ManageDepots = 'manage-depots';
    case ManageRoutes = 'manage-routes';
    case ManageSchedules = 'manage-schedules';
    case ManageFleet = 'manage-fleet';
    case OperateTrips = 'operate-trips';
    case LogFuelAndMaintenance = 'log-fuel-maintenance';
    case ViewReports = 'view-reports';
}
