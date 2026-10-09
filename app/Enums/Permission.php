<?php

namespace App\Enums;

/**
 * Fine-grained abilities. Each case is registered as a Gate in
 * AppServiceProvider, so views and routes can use @can('manage-routes').
 */
enum Permission: string
{
    // Administration
    case ManageUsers = 'manage-users';
    case ManageDepots = 'manage-depots';

    // Master data: add, edit and delete
    case ManageRoutes = 'manage-routes';
    case ManageSchedules = 'manage-schedules';
    case ManageFleet = 'manage-fleet';

    // Browse the bus, driver, route and timetable lists of the depot
    case ViewDepotRecords = 'view-depot-records';

    // Daily operations
    case AssignTrips = 'assign-trips';          // generate trips, swap bus or driver
    case RecordDelays = 'record-delays';
    case OperateTrips = 'operate-trips';        // departure, arrival, cancellation
    case CorrectTrips = 'correct-trips';        // edit any trip and its activity log

    // Fuel and maintenance
    case LogFuelAndMaintenance = 'log-fuel-maintenance';        // record and update
    case ManageFuelAndMaintenance = 'manage-fuel-maintenance';  // delete

    // Reporting
    case ViewReports = 'view-reports';                          // operational reports
    case ViewManagementReports = 'view-management-reports';     // cost reports
}
